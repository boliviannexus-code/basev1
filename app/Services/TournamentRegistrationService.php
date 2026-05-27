<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Validation\ValidationException;

class TournamentRegistrationService
{
    public function groupedByTournament(?int $divisionId = null): SupportCollection
    {
        return CompanyContext::scope(TournamentRegistration::query())
            ->with(['company', 'division', 'tournament.season', 'tournament.division', 'tournament.category', 'team'])
            ->when($divisionId, fn ($query, $divisionId) => $query->where('division_id', $divisionId))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('tournament_id');
    }

    public function create(array $data): TournamentRegistration
    {
        $data = $this->normalizeContext($data);
        $data['status'] = $data['status'] ?? 'registered';

        if (TournamentRegistration::query()
            ->where('division_id', $data['division_id'])
            ->where('team_id', $data['team_id'])
            ->whereNull('deleted_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'team_id' => 'Este equipo ya esta inscrito en un torneo de esta division.',
            ]);
        }

        return TournamentRegistration::query()->create($data);
    }

    public function update(TournamentRegistration $registration, array $data): TournamentRegistration
    {
        $this->ensureVisible($registration);

        $registration->update([
            'status' => $data['status'] ?? $registration->status,
            'notes' => $data['notes'] ?? null,
        ]);

        return $registration->refresh();
    }

    public function delete(TournamentRegistration $registration): bool
    {
        $this->ensureVisible($registration);

        return (bool) $registration->delete();
    }

    public function ensureVisible(TournamentRegistration $registration): void
    {
        abort_unless(CompanyContext::belongsToUser($registration->company_id, auth()->user()), 403);
    }

    public function tournamentsForSelect(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Tournament::query())
            ->with(['season', 'division', 'category'])
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();
    }

    public function divisionsForSelect(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Division::query())
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function teamsForSelect(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Team::query())
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function searchTeams(?string $query, ?int $tournamentId = null, int $limit = 12): Collection
    {
        $query = trim((string) $query);
        $companyId = CompanyContext::id();
        $tournamentCompanyId = null;

        if ($tournamentId) {
            $tournament = Tournament::query()->whereKey($tournamentId)->first();

            if (! $tournament || ! CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
                return new Collection;
            }

            $tournamentCompanyId = $tournament->company_id;
        }

        return CompanyContext::scope(Team::query())
            ->when($companyId, fn ($queryBuilder, $companyId) => $queryBuilder->where('company_id', $companyId))
            ->when($tournamentCompanyId, fn ($queryBuilder, $companyId) => $queryBuilder->where('company_id', $companyId))
            ->when($query !== '', function ($queryBuilder) use ($query): void {
                $queryBuilder->where('name_normalized', 'like', '%'.Team::normalizeName($query).'%');
            })
            ->where('is_active', true)
            ->when($tournament ?? null, function ($queryBuilder, Tournament $tournament): void {
                $queryBuilder->whereDoesntHave('registrations', function ($query) use ($tournament): void {
                    $query->where('division_id', $tournament->division_id)
                        ->whereNull('deleted_at');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'company_id']);
    }

    private function normalizeContext(array $data): array
    {
        $tournament = Tournament::query()->whereKey($data['tournament_id'] ?? null)->first();
        $team = Team::query()->whereKey($data['team_id'] ?? null)->first();

        if (! $tournament || ! CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
            throw ValidationException::withMessages([
                'tournament_id' => 'Selecciona un torneo de la liga deportiva activa.',
            ]);
        }

        if (! $team || ! CompanyContext::belongsToUser($team->company_id, auth()->user())) {
            throw ValidationException::withMessages([
                'team_id' => 'Selecciona un equipo de la liga deportiva activa.',
            ]);
        }

        if ((int) $team->company_id !== (int) $tournament->company_id) {
            throw ValidationException::withMessages([
                'team_id' => 'El equipo debe pertenecer a la misma liga deportiva del torneo.',
            ]);
        }

        $data['company_id'] = $tournament->company_id;
        $data['division_id'] = $tournament->division_id;

        return $data;
    }
}
