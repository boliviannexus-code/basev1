<?php

namespace App\Services;

use App\Models\Player;
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
            ->with(['season', 'division', 'category'])
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
            ->whereHas('registrations', function ($query) use ($tournament): void {
                $query->where('tournament_id', $tournament->id)
                    ->where('status', 'registered')
                    ->whereNull('deleted_at');
            })
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
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

        $tournament = Tournament::query()->whereKey($tournamentId)->first();
        $selectedTeam = Team::query()->whereKey($teamId)->first();
        $currentTeamPlayer = null;
        $habilitation = null;

        if ($tournament && CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
            $currentTeamPlayer = TeamPlayer::query()
                ->with(['team', 'division'])
                ->where('company_id', $tournament->company_id)
                ->where('division_id', $tournament->division_id)
                ->where('player_id', $player->id)
                ->where('status', TeamPlayer::STATUS_ACTIVE)
                ->whereNull('deleted_at')
                ->first();

            $habilitation = TournamentTeamPlayer::query()
                ->with('team')
                ->where('company_id', $tournament->company_id)
                ->where('tournament_id', $tournament->id)
                ->where('player_id', $player->id)
                ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
                ->whereNull('deleted_at')
                ->first();
        }

        $status = 'No afiliado';
        $statusTone = 'secondary';

        if ($habilitation) {
            $status = 'Habilitado';
            $statusTone = 'success';
        } elseif ($currentTeamPlayer) {
            $status = 'Solo afiliado';
            $statusTone = 'warning';
        }

        return [
            'found' => true,
            'player' => [
                'id' => $player->id,
                'ci' => $player->ci,
                'first_name' => $player->first_name,
                'last_name' => $player->last_name,
                'full_name' => $player->full_name,
                'birth_date' => $player->birth_date?->format('Y-m-d'),
                'internal_code' => $player->internal_code,
                'age' => $player->age(),
                'is_active' => $player->is_active,
            ],
            'current_team' => $currentTeamPlayer ? [
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
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $teamPlayer->team_id)
            ->where('player_id', $teamPlayer->player_id)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNull('deleted_at')
            ->first();

        if ($existing) {
            return filled($existing->qr_code_path) ? $existing : $this->qrCodes->generateForHabilitation($existing);
        }

        $habilitation = DB::transaction(fn () => TournamentTeamPlayer::query()->create([
            'company_id' => $tournament->company_id,
            'tournament_id' => $tournament->id,
            'tournament_registration_id' => $registration->id,
            'team_id' => $teamPlayer->team_id,
            'player_id' => $teamPlayer->player_id,
            'team_player_id' => $teamPlayer->id,
            'status' => TournamentTeamPlayer::STATUS_ENABLED,
            'enabled_at' => now(),
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
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);
        abort_unless(CompanyContext::belongsToUser($team->company_id, auth()->user()), 403);
        abort_unless((int) $tournament->company_id === (int) $team->company_id, 403);
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
