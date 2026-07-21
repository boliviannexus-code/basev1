<?php

namespace App\Services;

use App\Models\LeagueSetting;
use App\Models\Player;
use App\Models\PlayerTransferRequest;
use App\Models\PlayerTransferSetting;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlayerHabilitationService
{
    public function __construct(
        private readonly PlayerQrCodeService $qrCodes
    ) {}

    public function tournamentsForSelect(): Collection
    {
        return CompanyContext::scope(Tournament::query())
            ->with(['season', 'division', 'categories'])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();
    }

    public function teamsForTournament(?Tournament $tournament): Collection
    {
        if (! $tournament) {
            return new Collection;
        }

        return CompanyContext::scope(Team::query())
            ->with(['registrations' => function ($query) use ($tournament): void {
                $query->with(['category'])
                    ->where('tournament_id', $tournament->id)
                    ->where('status', 'registered')
                    ->whereNull('deleted_at');
            }])
            ->withCount(['teamPlayers as roster_players_count' => function ($query) use ($tournament): void {
                $query->where('division_id', $tournament->division_id)
                    ->where('status', TeamPlayer::STATUS_ACTIVE)
                    ->whereNull('deleted_at');
            }])
            ->whereHas('registrations', function ($query) use ($tournament): void {
                $query->where('tournament_id', $tournament->id)
                    ->where('status', 'registered')
                    ->whereNull('deleted_at');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function tournamentContext(Tournament $tournament): array
    {
        $this->ensureTournamentVisible($tournament);
        $tournament->loadMissing(['season', 'division', 'categories']);
        $teams = $this->teamsForTournament($tournament);
        $enabledCounts = TournamentTeamPlayer::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->select('team_id', DB::raw('COUNT(*) as total'))
            ->groupBy('team_id')
            ->pluck('total', 'team_id');

        return [
            'enabledCounts' => $enabledCounts,
            'teams' => $teams,
            'tournament' => $tournament,
        ];
    }

    public function teamContext(Tournament $tournament, Team $team, ?string $search = null): array
    {
        $this->ensureSameLeague($tournament, $team);
        abort_unless($this->registrationFor($tournament, $team), 404);
        $tournament->loadMissing(['season', 'division', 'categories']);
        $team->loadMissing(['company']);

        return [
            'enabledPlayers' => $this->enabledPlayers($tournament, $team),
            'registration' => $this->registrationFor($tournament, $team),
            'rosterPlayers' => $this->rosterPlayers($tournament, $team, $search),
            'search' => trim((string) $search),
            'team' => $team,
            'tournament' => $tournament,
        ];
    }

    public function context(?int $tournamentId, ?int $teamId, ?string $search = null): array
    {
        $tournaments = $this->tournamentsForSelect();
        $tournament = $tournamentId
            ? $tournaments->firstWhere('id', $tournamentId)
            : $tournaments->first();
        $teams = $this->teamsForTournament($tournament);
        $team = $teamId ? $teams->firstWhere('id', $teamId) : $teams->first();
        $registration = $tournament && $team ? $this->registrationFor($tournament, $team) : null;

        return [
            'enabledPlayers' => $tournament && $team ? $this->enabledPlayers($tournament, $team) : new Collection,
            'registration' => $registration,
            'rosterPlayers' => $tournament && $team ? $this->rosterPlayers($tournament, $team, $search) : new Collection,
            'search' => trim((string) $search),
            'team' => $team,
            'teams' => $teams,
            'tournament' => $tournament,
            'tournaments' => $tournaments,
        ];
    }

    public function enabledPlayers(Tournament $tournament, Team $team): Collection
    {
        $this->ensureSameLeague($tournament, $team);

        return TournamentTeamPlayer::query()
            ->with(['player', 'teamPlayer'])
            ->where('company_id', $tournament->company_id)
            ->whereHas('player', fn ($query) => $query->where('company_id', $tournament->company_id))
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $team->id)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->orderByDesc('enabled_at')
            ->get();
    }

    public function rosterPlayers(Tournament $tournament, Team $team, ?string $search = null): Collection
    {
        $this->ensureSameLeague($tournament, $team);
        $search = trim((string) $search);

        return TeamPlayer::query()
            ->with(['player'])
            ->where('company_id', $tournament->company_id)
            ->where('division_id', $tournament->division_id)
            ->where('team_id', $team->id)
            ->where('status', TeamPlayer::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str($search)->lower()->toString().'%';
                $normalizedCi = '%'.Player::normalizeCi($search).'%';
                $query->whereHas('player', function ($playerQuery) use ($like, $normalizedCi): void {
                    $playerQuery
                        ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(maternal_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(internal_code) LIKE ?', [$like])
                        ->orWhere('ci_normalized', 'like', $normalizedCi);
                });
            })
            ->orderByDesc('created_at')
            ->get();
    }

    public function playerLookup(string $ci, ?int $tournamentId, ?int $teamId): array
    {
        $normalizedCi = Player::normalizeCi($ci);

        if ($normalizedCi === '') {
            return ['found' => false];
        }

        $player = Player::query()
            ->where('ci_normalized', $normalizedCi)
            ->first();

        if (! $player) {
            return ['found' => false];
        }

        $tournament = Tournament::query()->with('division')->whereKey($tournamentId)->first();
        $selectedTeam = Team::query()->whereKey($teamId)->first();
        $currentTeamPlayer = null;
        $habilitation = null;
        $belongsToSelectedTeam = false;
        $belongsToOtherTeam = false;
        $enabledInSelectedTeam = false;
        $enabledInOtherTeam = false;
        $pendingTransfer = null;

        if ($tournament && CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
            $currentTeamPlayer = TeamPlayer::query()
                ->with(['team', 'division'])
                ->where('company_id', $tournament->company_id)
                ->where('division_id', $tournament->division_id)
                ->where('player_id', $player->id)
                ->where('status', TeamPlayer::STATUS_ACTIVE)
                ->whereNull('deleted_at')
                ->first();

            $belongsToSelectedTeam = $currentTeamPlayer
                && $selectedTeam
                && (int) $selectedTeam->id === (int) $currentTeamPlayer->team_id;
            $belongsToOtherTeam = $currentTeamPlayer
                && $selectedTeam
                && (int) $selectedTeam->id !== (int) $currentTeamPlayer->team_id;

            $habilitation = TournamentTeamPlayer::query()
                ->with('team')
                ->where('company_id', $tournament->company_id)
                ->where('tournament_id', $tournament->id)
                ->where('player_id', $player->id)
                ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
                ->whereNull('deleted_at')
                ->first();

            $enabledInSelectedTeam = $habilitation
                && $selectedTeam
                && (int) $selectedTeam->id === (int) $habilitation->team_id;
            $enabledInOtherTeam = $habilitation
                && $selectedTeam
                && (int) $selectedTeam->id !== (int) $habilitation->team_id;

            if ($selectedTeam) {
                $pendingTransfer = PlayerTransferRequest::query()
                    ->where('company_id', $tournament->company_id)
                    ->where('division_id', $tournament->division_id)
                    ->where('player_id', $player->id)
                    ->where('to_team_id', $selectedTeam->id)
                    ->where('status', PlayerTransferRequest::STATUS_PENDING)
                    ->whereNull('deleted_at')
                    ->first();
            }
        }

        $status = 'No afiliado';
        $statusTone = 'secondary';
        $transferBlockedReason = null;

        if ($enabledInOtherTeam) {
            $status = 'Habilitado en otro equipo';
            $statusTone = 'danger';
            $transferBlockedReason = 'El jugador ya esta habilitado en este torneo con otro equipo.';
        } elseif ($habilitation) {
            $status = 'Habilitado';
            $statusTone = 'success';
        } elseif ($belongsToOtherTeam) {
            $status = 'En otro equipo';
            $statusTone = 'warning';
        } elseif ($currentTeamPlayer) {
            $status = 'En plantilla';
            $statusTone = 'warning';
        }

        $age = $player->age();
        $ageOk = $tournament?->division
            && $age !== null
            && $age >= $tournament->division->min_age
            && $age <= $tournament->division->max_age;
        $ageBlockedReason = null;

        if (! $tournament?->division) {
            $ageBlockedReason = 'El torneo no tiene una division valida para verificar la edad.';
        } elseif ($age === null) {
            $ageBlockedReason = 'El jugador debe tener fecha de nacimiento registrada para validar la categoria.';
        } elseif (! $ageOk) {
            $ageBlockedReason = "El jugador tiene {$age} anos y no cumple el rango de la categoria ({$tournament->division->min_age} a {$tournament->division->max_age} anos).";
        }

        $canRequestTransfer = $belongsToOtherTeam && ! $habilitation;
        $canAffiliate = ! $belongsToOtherTeam && ! $enabledInOtherTeam && $ageOk;
        $canEnable = $belongsToSelectedTeam && ! $habilitation && $ageOk;
        $transferRequestUrl = null;

        if ($pendingTransfer) {
            $canRequestTransfer = false;
            $transferBlockedReason = "Ya existe una solicitud de pase pendiente ({$pendingTransfer->code}).";
        } elseif ($canRequestTransfer && $tournament && $selectedTeam) {
            $setting = PlayerTransferSetting::query()->where('company_id', $tournament->company_id)->first();
            $transferFee = LeagueSetting::query()
                ->where('company_id', $tournament->company_id)
                ->value('transfer_fee') ?? $setting?->fee_amount;

            if (blank($tournament->company?->code)) {
                $canRequestTransfer = false;
                $transferBlockedReason = 'La liga debe tener codigo de 3 letras antes de solicitar pases.';
            } elseif ($transferFee === null || (float) $transferFee <= 0) {
                $canRequestTransfer = false;
                $transferBlockedReason = 'Configura el precio del pase antes de solicitar pases.';
            } else {
                $transferRequestUrl = route('player-transfers.create', [
                    'tournament_id' => $tournament->id,
                    'to_team_id' => $selectedTeam->id,
                    'player_id' => $player->id,
                ]);
            }
        }

        return [
            'found' => true,
            'player' => [
                'id' => $player->id,
                'ci' => $player->ci,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'maternal_name' => $player->maternal_name,
                'full_name' => $player->full_name,
                'birth_date' => $player->birth_date?->format('Y-m-d'),
                'internal_code' => $player->internal_code,
                'age' => $player->age(),
                'is_active' => $player->is_active,
            ],
            'current_team' => $currentTeamPlayer ? [
                'team_player_id' => $currentTeamPlayer->id,
                'id' => $currentTeamPlayer->team_id,
                'name' => $currentTeamPlayer->team?->name,
                'division' => $currentTeamPlayer->division?->name,
                'joined_at' => $currentTeamPlayer->joined_at?->format('Y-m-d'),
                'is_selected_team' => $selectedTeam && (int) $selectedTeam->id === (int) $currentTeamPlayer->team_id,
            ] : null,
            'habilitation' => $habilitation ? [
                'id' => $habilitation->id,
                'team' => $habilitation->team?->name,
                'enabled_at' => $habilitation->enabled_at?->format('Y-m-d H:i'),
            ] : null,
            'status' => [
                'label' => $status,
                'tone' => $statusTone,
            ],
            'actions' => [
                'can_affiliate' => $canAffiliate,
                'can_enable' => $canEnable,
                'can_request_transfer' => $canRequestTransfer,
                'age_valid' => $ageOk,
                'age_blocked_reason' => $ageBlockedReason,
                'is_selected_team_roster' => $belongsToSelectedTeam,
                'is_other_team_roster' => $belongsToOtherTeam,
                'enabled_in_selected_team' => $enabledInSelectedTeam,
                'enabled_in_other_team' => $enabledInOtherTeam,
                'transfer_blocked_reason' => $transferBlockedReason,
                'transfer_request_url' => $transferRequestUrl,
            ],
        ];
    }

    public function enable(array $data): TournamentTeamPlayer
    {
        $tournament = Tournament::query()->with('division')->whereKey($data['tournament_id'] ?? null)->first();
        $teamPlayer = TeamPlayer::query()->with(['player', 'team'])->whereKey($data['team_player_id'] ?? null)->first();

        if (! $tournament || ! CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
            throw ValidationException::withMessages(['tournament_id' => 'Selecciona un torneo de la liga activa.']);
        }

        if (! $teamPlayer || ! CompanyContext::belongsToUser($teamPlayer->company_id, auth()->user())) {
            throw ValidationException::withMessages(['team_player_id' => 'Selecciona un jugador inscrito al equipo.']);
        }

        if ((int) $teamPlayer->company_id !== (int) $tournament->company_id || (int) $teamPlayer->division_id !== (int) $tournament->division_id) {
            throw ValidationException::withMessages(['team_player_id' => 'El jugador debe estar inscrito al equipo en la division del torneo.']);
        }

        if ($teamPlayer->status !== TeamPlayer::STATUS_ACTIVE || $teamPlayer->deleted_at !== null) {
            throw ValidationException::withMessages(['team_player_id' => 'El jugador no esta activo en la plantilla del equipo.']);
        }

        $registration = $this->registrationFor($tournament, $teamPlayer->team);

        if (! $registration) {
            throw ValidationException::withMessages(['team_id' => 'El equipo debe estar inscrito en el torneo antes de habilitar jugadores.']);
        }

        $this->ensurePlayerAge($teamPlayer->player, $tournament);

        $existing = TournamentTeamPlayer::query()
            ->with('team')
            ->where('tournament_id', $tournament->id)
            ->where('player_id', $teamPlayer->player_id)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            if ((int) $existing->team_id !== (int) $teamPlayer->team_id) {
                $teamName = $existing->team?->name ?? 'otro equipo';

                throw ValidationException::withMessages([
                    'team_player_id' => "El jugador ya esta habilitado en este torneo con {$teamName}.",
                ]);
            }

            return filled($existing->qr_code_path) ? $existing : $this->qrCodes->generateForHabilitation($existing);
        }

        $this->ensureEnabledPlayerLimit($tournament, $registration);

        $habilitation = DB::transaction(fn () => TournamentTeamPlayer::query()->create([
            'company_id' => $tournament->company_id,
            'tournament_id' => $tournament->id,
            'tournament_registration_id' => $registration->id,
            'team_id' => $teamPlayer->team_id,
            'player_id' => $teamPlayer->player_id,
            'team_player_id' => $teamPlayer->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
            'enabled_at' => now(),
            'enabled_by' => auth()->id(),
            'notes' => $data['notes'] ?? null,
        ]));

        return $this->qrCodes->generateForHabilitation($habilitation);
    }

    public function disable(TournamentTeamPlayer $habilitation): TournamentTeamPlayer
    {
        abort_unless(CompanyContext::belongsToUser($habilitation->company_id, auth()->user()), 403);

        $habilitation->forceFill([
            'status' => TournamentTeamPlayer::STATUS_DISABLED,
            'deleted_at' => now(),
        ])->save();

        return $habilitation;
    }

    private function ensureEnabledPlayerLimit(Tournament $tournament, TournamentRegistration $registration): void
    {
        $limit = (int) (LeagueSetting::query()
            ->where('company_id', $tournament->company_id)
            ->value('max_enabled_players_per_team_category') ?? 0);

        if ($limit <= 0) {
            return;
        }

        $enabledCount = TournamentTeamPlayer::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('tournament_registration_id', $registration->id)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->count();

        if ($enabledCount >= $limit) {
            throw ValidationException::withMessages([
                'team_player_id' => "Este equipo ya alcanzo el limite de {$limit} habilitado(s) para esta categoria.",
            ]);
        }
    }

    public function registrationFor(Tournament $tournament, Team $team): ?TournamentRegistration
    {
        $this->ensureSameLeague($tournament, $team);

        return TournamentRegistration::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('division_id', $tournament->division_id)
            ->where('team_id', $team->id)
            ->where('status', 'registered')
            ->whereNull('deleted_at')
            ->first();
    }

    private function ensureSameLeague(Tournament $tournament, Team $team): void
    {
        $this->ensureTournamentVisible($tournament);
        abort_unless(CompanyContext::belongsToUser($team->company_id, auth()->user()), 403);
        abort_unless((int) $tournament->company_id === (int) $team->company_id, 403);
    }

    private function ensureTournamentVisible(Tournament $tournament): void
    {
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);
        abort_unless($tournament->is_active, 404);
    }

    private function ensurePlayerAge(Player $player, Tournament $tournament): void
    {
        $age = $player->age();
        $division = $tournament->division;

        if ($age === null || ! $division) {
            throw ValidationException::withMessages(['player_id' => 'El jugador debe tener fecha de nacimiento registrada.']);
        }

        if ($age < $division->min_age || $age > $division->max_age) {
            throw ValidationException::withMessages([
                'player_id' => "El jugador tiene {$age} anos y no cumple el rango de la division ({$division->min_age} a {$division->max_age}).",
            ]);
        }
    }
}
