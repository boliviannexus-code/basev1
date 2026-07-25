<?php

namespace App\Services;

use App\Models\Company;
use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerTransferRequest;
use App\Models\PlayerTransferSetting;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerTransferService
{
    public function requests(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(PlayerTransferRequest::query())
            ->with(['company', 'player', 'division', 'fromTeam', 'toTeam', 'requester', 'reviewer'])
            ->latest()
            ->paginate($perPage);
    }

    public function settings(): Collection
    {
        return CompanyContext::scope(Company::query(), column: 'id')
            ->with(['leagueSetting', 'playerTransferSetting'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function settingForCompany(?int $companyId = null): PlayerTransferSetting
    {
        $companyId = $this->resolveCompanyId($companyId);

        return PlayerTransferSetting::query()->firstOrCreate(
            ['company_id' => $companyId],
            [
                'fee_amount' => $this->transferFeeForCompany($companyId),
                'next_sequence' => 1,
            ]
        );
    }

    public function updateSetting(array $data): PlayerTransferSetting
    {
        $companyId = $this->resolveCompanyId((int) ($data['company_id'] ?? 0));

        LeagueSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            ['transfer_fee' => $data['fee_amount']]
        );

        return PlayerTransferSetting::query()->updateOrCreate(
            ['company_id' => $companyId],
            [
                'fee_amount' => $data['fee_amount'],
                'next_sequence' => $data['next_sequence'] ?? 1,
            ]
        );
    }

    public function requestFormContext(int $tournamentId, int $teamId, int $playerId): array
    {
        $context = $this->transferContext($tournamentId, $teamId, $playerId);

        return $context + [
            'setting' => $this->configuredSetting($context['company']),
        ];
    }

    public function create(array $data): PlayerTransferRequest
    {
        return DB::transaction(function () use ($data): PlayerTransferRequest {
            $context = $this->transferContext(
                (int) $data['tournament_id'],
                (int) $data['to_team_id'],
                (int) $data['player_id']
            );
            $company = $context['company'];
            $setting = PlayerTransferSetting::query()
                ->where('company_id', $company->id)
                ->lockForUpdate()
                ->first();

            if (! $setting) {
                $setting = PlayerTransferSetting::query()->create([
                    'company_id' => $company->id,
                    'fee_amount' => $this->transferFeeForCompany($company->id),
                    'next_sequence' => 1,
                ]);
            } elseif ($setting->fee_amount === null || (float) $setting->fee_amount <= 0) {
                $setting->forceFill(['fee_amount' => $this->transferFeeForCompany($company->id)])->save();
            }

            $this->ensureSettingReady($setting, $company);
            $this->ensureNoPendingDuplicate($company->id, $context['tournament']->division_id, $context['player']->id, $context['toTeam']->id);

            $sequence = (int) $setting->next_sequence;
            $code = $company->code.'PASE'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            $request = PlayerTransferRequest::query()->create([
                'company_id' => $company->id,
                'player_id' => $context['player']->id,
                'division_id' => $context['tournament']->division_id,
                'from_team_id' => $context['fromTeamPlayer']->team_id,
                'to_team_id' => $context['toTeam']->id,
                'from_team_player_id' => $context['fromTeamPlayer']->id,
                'requested_by' => auth()->id(),
                'code' => $code,
                'sequence' => $sequence,
                'status' => PlayerTransferRequest::STATUS_PENDING,
                'fee_amount' => $setting->fee_amount,
                'requested_note' => $data['requested_note'],
            ]);

            $setting->forceFill(['next_sequence' => $sequence + 1])->save();

            return $request->load(['player', 'fromTeam', 'toTeam', 'division']);
        });
    }

    public function approve(PlayerTransferRequest $request, int $reviewerId, ?string $notes = null): PlayerTransferRequest
    {
        $this->ensureVisible($request);
        $this->ensurePending($request);

        return DB::transaction(function () use ($request, $reviewerId, $notes): PlayerTransferRequest {
            $request = PlayerTransferRequest::query()
                ->with(['player', 'division'])
                ->whereKey($request->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensurePending($request);

            $fromTeamPlayer = TeamPlayer::query()
                ->where('company_id', $request->company_id)
                ->where('division_id', $request->division_id)
                ->where('player_id', $request->player_id)
                ->where('team_id', $request->from_team_id)
                ->where('status', TeamPlayer::STATUS_ACTIVE)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $fromTeamPlayer) {
                throw ValidationException::withMessages([
                    'transfer' => 'El jugador ya no pertenece activamente al equipo origen.',
                ]);
            }

            $fromTeamPlayer->update([
                'status' => TeamPlayer::STATUS_INACTIVE,
                'ended_at' => now()->toDateString(),
                'notes' => trim(($fromTeamPlayer->notes ? $fromTeamPlayer->notes."\n" : '').'Salida por pase '.$request->code),
            ]);

            $toTeamPlayer = TeamPlayer::query()->create([
                'company_id' => $request->company_id,
                'division_id' => $request->division_id,
                'team_id' => $request->to_team_id,
                'player_id' => $request->player_id,
                'status' => TeamPlayer::STATUS_ACTIVE,
                'joined_at' => now()->toDateString(),
                'notes' => 'Ingreso por pase '.$request->code,
            ]);

            $request->update([
                'to_team_player_id' => $toTeamPlayer->id,
                'reviewed_by' => $reviewerId,
                'status' => PlayerTransferRequest::STATUS_APPROVED,
                'collected_amount' => $request->fee_amount,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            return $request->refresh()->load(['player', 'division', 'fromTeam', 'toTeam', 'reviewer']);
        });
    }

    public function reject(PlayerTransferRequest $request, int $reviewerId, ?string $notes = null): PlayerTransferRequest
    {
        $this->ensureVisible($request);
        $this->ensurePending($request);

        $request->update([
            'reviewed_by' => $reviewerId,
            'status' => PlayerTransferRequest::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        return $request->refresh();
    }

    public function ensureVisible(PlayerTransferRequest $request): void
    {
        abort_unless(CompanyContext::belongsToUser($request->company_id, auth()->user()), 403);
    }

    public function transferContext(int $tournamentId, int $toTeamId, int $playerId): array
    {
        $tournament = Tournament::query()->with('division')->whereKey($tournamentId)->first();
        $toTeam = Team::query()->whereKey($toTeamId)->first();
        $player = Player::query()->whereKey($playerId)->first();

        if (! $tournament || ! CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
            throw ValidationException::withMessages(['tournament_id' => 'Selecciona un torneo de la liga activa.']);
        }

        if (! $toTeam || ! CompanyContext::belongsToUser($toTeam->company_id, auth()->user())) {
            throw ValidationException::withMessages(['to_team_id' => 'Selecciona un equipo solicitante de la liga activa.']);
        }

        if (! $player) {
            throw ValidationException::withMessages(['player_id' => 'Selecciona un jugador de la liga activa.']);
        }

        if ((int) $tournament->company_id !== (int) $toTeam->company_id) {
            throw ValidationException::withMessages(['player_id' => 'El equipo y torneo deben pertenecer a la misma liga.']);
        }

        $registered = TournamentRegistration::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $toTeam->id)
            ->where('status', 'registered')
            ->whereNull('deleted_at')
            ->exists();

        if (! $registered) {
            throw ValidationException::withMessages(['to_team_id' => 'El equipo solicitante debe estar inscrito en el torneo.']);
        }

        $fromTeamPlayer = TeamPlayer::query()
            ->with(['team', 'division'])
            ->where('company_id', $tournament->company_id)
            ->where('division_id', $tournament->division_id)
            ->where('player_id', $player->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->first();

        if (! $fromTeamPlayer) {
            throw ValidationException::withMessages(['player_id' => 'El jugador no pertenece a otro equipo activo en esta division.']);
        }

        if ((int) $fromTeamPlayer->team_id === (int) $toTeam->id) {
            throw ValidationException::withMessages(['player_id' => 'El jugador ya pertenece al equipo solicitante.']);
        }

        return [
            'company' => $tournament->company,
            'tournament' => $tournament,
            'toTeam' => $toTeam,
            'player' => $player,
            'fromTeamPlayer' => $fromTeamPlayer,
        ];
    }

    private function configuredSetting(Company $company): PlayerTransferSetting
    {
        $setting = PlayerTransferSetting::query()->firstOrCreate(
            ['company_id' => $company->id],
            [
                'fee_amount' => $this->transferFeeForCompany($company->id),
                'next_sequence' => 1,
            ]
        );

        if ($setting->fee_amount === null || (float) $setting->fee_amount <= 0) {
            $setting->forceFill(['fee_amount' => $this->transferFeeForCompany($company->id)])->save();
        }

        $this->ensureSettingReady($setting, $company);

        return $setting;
    }

    private function ensureSettingReady(?PlayerTransferSetting $setting, Company $company): void
    {
        if (blank($company->code)) {
            throw ValidationException::withMessages(['company_id' => 'La liga debe tener codigo de 3 letras antes de solicitar pases.']);
        }

        if (! $setting || $setting->fee_amount === null || (float) $setting->fee_amount <= 0) {
            throw ValidationException::withMessages(['fee_amount' => 'Configura el precio del pase antes de solicitar pases.']);
        }
    }

    private function transferFeeForCompany(int $companyId): ?float
    {
        $fee = LeagueSetting::query()
            ->where('company_id', $companyId)
            ->value('transfer_fee');

        return $fee !== null ? (float) $fee : null;
    }

    private function ensureNoPendingDuplicate(int $companyId, int $divisionId, int $playerId, int $toTeamId): void
    {
        $exists = PlayerTransferRequest::query()
            ->where('company_id', $companyId)
            ->where('division_id', $divisionId)
            ->where('player_id', $playerId)
            ->where('to_team_id', $toTeamId)
            ->where('status', PlayerTransferRequest::STATUS_PENDING)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['player_id' => 'Ya existe una solicitud de pase pendiente para este jugador y equipo.']);
        }
    }

    private function ensurePending(PlayerTransferRequest $request): void
    {
        if ($request->status !== PlayerTransferRequest::STATUS_PENDING) {
            throw ValidationException::withMessages(['transfer' => 'La solicitud de pase ya fue revisada.']);
        }
    }

    private function resolveCompanyId(?int $companyId = null): int
    {
        $activeCompanyId = CompanyContext::id();

        if ($activeCompanyId !== null && $activeCompanyId > 0) {
            return $activeCompanyId;
        }

        $company = Company::query()->whereKey($companyId)->first();

        if (! $company || ! CompanyContext::belongsToUser($company->id, auth()->user())) {
            throw ValidationException::withMessages(['company_id' => 'Selecciona una liga deportiva valida.']);
        }

        return $company->id;
    }
}
