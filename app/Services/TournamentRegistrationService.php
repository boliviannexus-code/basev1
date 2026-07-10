<?php

namespace App\Services;

use App\Models\Division;
use App\Models\DivisionCategory;
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
            ->with(['company', 'division', 'category', 'tournament.season', 'tournament.division', 'tournament.categories', 'team'])
            ->when($divisionId, fn ($query, $divisionId) => $query->where('division_id', $divisionId))
            ->orderBy('tournament_id')
            ->orderBy('category_id')
            ->orderBy('series')
            ->orderBy('team_number')
            ->orderBy('created_at')
            ->get()
            ->groupBy('tournament_id');
    }

    public function create(array $data): TournamentRegistration
    {
        $data = $this->normalizeContext($data);
        $data['status'] = $data['status'] ?? 'registered';
        $data['series'] = $data['series'] ?? 'unica';
        $data['team_number'] = $this->nextTeamNumber((int) $data['tournament_id'], (int) $data['category_id'], $data['series']);

        if (TournamentRegistration::query()
            ->where('tournament_id', $data['tournament_id'])
            ->where('team_id', $data['team_id'])
            ->whereNull('deleted_at')
            ->exists()) {
            throw ValidationException::withMessages([
                'team_id' => 'Este equipo ya esta inscrito en este torneo.',
            ]);
        }

        return TournamentRegistration::query()->create($data);
    }

    public function updateTeamNumber(TournamentRegistration $registration, int $teamNumber): TournamentRegistration
    {
        $this->ensureVisible($registration);

        $exists = TournamentRegistration::query()
            ->where('tournament_id', $registration->tournament_id)
            ->where('category_id', $registration->category_id)
            ->where('series', $registration->series)
            ->where('team_number', $teamNumber)
            ->whereKeyNot($registration->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'team_number' => 'Ya existe otro equipo con este numero en esta categoria y serie.',
            ]);
        }

        $registration->update(['team_number' => $teamNumber]);

        return $registration->refresh();
    }

    public function update(TournamentRegistration $registration, array $data): TournamentRegistration
    {
        $this->ensureVisible($registration);
        $categoryId = $this->validCategoryIdForTournament($registration->tournament, $data['category_id'] ?? null);

        $registration->update([
            'category_id' => $categoryId,
            'series' => $data['series'] ?? $registration->series ?? 'unica',
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
            ->with(['season', 'division', 'categories'])
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

    public function teamsForRegistrationList(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Team::query())
            ->with(['registrations' => function ($query): void {
                $query->whereNull('deleted_at')
                    ->with(['tournament.season', 'tournament.division', 'category']);
            }])
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function teamForRegistration(?int $teamId): ?Team
    {
        if (! $teamId) {
            return null;
        }

        $team = Team::query()->whereKey($teamId)->first();

        if (! $team || ! CompanyContext::belongsToUser($team->company_id, auth()->user())) {
            return null;
        }

        return $team;
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
                    $query->where('tournament_id', $tournament->id)
                        ->whereNull('deleted_at');
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'company_id']);
    }

    public function categoriesForTournament(Tournament $tournament, ?int $teamId = null): Collection
    {
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);

        if ($teamId && $this->teamIsRegisteredInTournament($teamId, $tournament)) {
            return new Collection;
        }

        return $tournament->categories()
            ->where('division_categories.is_active', true)
            ->orderBy('division_categories.name')
            ->get(['division_categories.id', 'division_categories.name']);
    }

    public function teamIsRegisteredInTournament(int $teamId, Tournament $tournament): bool
    {
        return TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->exists();
    }

    private function normalizeContext(array $data): array
    {
        $tournament = Tournament::query()->whereKey($data['tournament_id'] ?? null)->first();
        $team = Team::query()->whereKey($data['team_id'] ?? null)->first();
        $category = DivisionCategory::query()->whereKey($data['category_id'] ?? null)->first();

        if (! $tournament || ! $tournament->is_active || ! CompanyContext::belongsToUser($tournament->company_id, auth()->user())) {
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
        $data['category_id'] = $this->validCategoryIdForTournament($tournament, $category?->id);

        return $data;
    }

    private function nextTeamNumber(int $tournamentId, int $categoryId, string $series): int
    {
        return (int) (TournamentRegistration::query()
            ->where('tournament_id', $tournamentId)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->whereNull('deleted_at')
            ->max('team_number') ?? 0) + 1;
    }

    private function validCategoryIdForTournament(?Tournament $tournament, mixed $categoryId): int
    {
        if (! $tournament || ! $categoryId || ! $tournament->categories()->whereKey((int) $categoryId)->exists()) {
            throw ValidationException::withMessages([
                'category_id' => 'Selecciona una categoria habilitada para este torneo.',
            ]);
        }

        return (int) $categoryId;
    }
}
