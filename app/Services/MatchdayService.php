<?php

namespace App\Services;

use App\Models\Court;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchdayDate;
use App\Models\MatchdayDateFiscal;
use App\Models\Season;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MatchdayService
{
    private const SECOND_PHASE_GROUP = '__second_phase';

    public function activeSeasons(): Collection
    {
        return CompanyContext::scope(Season::query())
            ->with('company')
            ->withCount('matchdays')
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('name')
            ->get();
    }

    public function seasonMatchdays(Season $season): Collection
    {
        $this->ensureSeasonVisible($season);

        return $season->matchdays()
            ->with('season')
            ->withCount(['dates', 'fixtureMatches'])
            ->orderByDesc('number')
            ->get();
    }

    public function configureContext(Matchday $matchday): array
    {
        $this->ensureMatchdayVisible($matchday);

        $matchday->loadMissing([
            'season.company',
            'dates' => fn ($query) => $query
                ->orderByRaw('COALESCE(sort_order, 2147483647)')
                ->orderBy('date')
                ->orderBy('id'),
            'dates.court',
            'dates.fixtureMatches.tournament',
            'dates.fixtureMatches.category',
            'dates.fixtureMatches.homeTeam',
            'dates.fixtureMatches.awayTeam',
            'dates.fiscals.team',
        ]);

        return [
            'matchday' => $matchday,
            'season' => $matchday->season,
            'dates' => $matchday->dates,
            'courts' => Court::query()
                ->where('company_id', $matchday->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    public function previewContext(Matchday $matchday): array
    {
        $context = $this->configureContext($matchday);

        $context['dates']->each(function (MatchdayDate $date): void {
            $date->setRelation(
                'fiscals',
                $date->fiscals
                    ->sortBy([['start_time', 'asc'], ['end_time', 'asc']])
                    ->values()
            );

            $matches = $date->fixtureMatches
                ->sortBy([['scheduled_time', 'asc'], ['match_number', 'asc']])
                ->values();

            $matches->each(function (FixtureMatch $match) use ($date): void {
                $match->setRelation('fiscalAssignment', $this->fiscalForScheduledMatch($date, $match));
            });

            $date->setRelation(
                'fixtureMatches',
                $matches
            );
        });

        return $context;
    }

    public function dateContext(MatchdayDate $date, array $filters = []): array
    {
        $this->ensureDateVisible($date);
        $date->loadMissing(['court', 'matchday.season.company']);

        $season = $date->matchday->season;
        $selectedTournamentId = (int) ($filters['tournament_id'] ?? 0);
        [$selectedCategoryId, $selectedSeries] = $this->selectedFixtureGroup($filters);
        $tournaments = $this->fixtureTournamentsForSeason($season);
        $selectedTournament = $tournaments->firstWhere('id', $selectedTournamentId);
        $fixtureGroups = $selectedTournament ? $this->fixtureGroupsForTournament($selectedTournament) : collect();
        $selectedFixtureGroup = $fixtureGroups->firstWhere('value', $selectedCategoryId.'|'.$selectedSeries);
        $selectedFiscalTournamentId = (int) ($filters['fiscal_tournament_id'] ?? 0);
        [$selectedFiscalCategoryId, $selectedFiscalSeries] = $this->selectedFixtureGroup($filters, 'fiscal_fixture_group', 'fiscal_category_id', 'fiscal_series');
        $selectedFiscalTournament = $tournaments->firstWhere('id', $selectedFiscalTournamentId);
        $fiscalFixtureGroups = $selectedFiscalTournament ? $this->fixtureGroupsForTournament($selectedFiscalTournament) : collect();
        $selectedFiscalFixtureGroup = $fiscalFixtureGroups->firstWhere('value', $selectedFiscalCategoryId.'|'.$selectedFiscalSeries);

        if (! $selectedFixtureGroup) {
            $selectedCategoryId = 0;
            $selectedSeries = '';
        }

        if (! $selectedFiscalFixtureGroup) {
            $selectedFiscalCategoryId = 0;
            $selectedFiscalSeries = '';
        }

        return [
            'date' => $date,
            'matchday' => $date->matchday,
            'season' => $season,
            'tournaments' => $tournaments,
            'fixtureGroups' => $fixtureGroups,
            'selectedTournamentId' => $selectedTournament?->id,
            'selectedFixtureGroup' => $selectedFixtureGroup['value'] ?? '',
            'selectedCategoryId' => $selectedFixtureGroup['category_id'] ?? null,
            'selectedSeries' => $selectedSeries,
            'availableMatches' => $selectedTournament && $selectedFixtureGroup
                ? $this->availableMatchesFor($selectedTournament, (int) $selectedFixtureGroup['category_id'], $selectedSeries)
                : collect(),
            'fiscalFixtureGroups' => $fiscalFixtureGroups,
            'selectedFiscalTournamentId' => $selectedFiscalTournament?->id,
            'selectedFiscalFixtureGroup' => $selectedFiscalFixtureGroup['value'] ?? '',
            'selectedFiscalCategoryId' => $selectedFiscalFixtureGroup['category_id'] ?? null,
            'selectedFiscalSeries' => $selectedFiscalSeries,
            'fiscalCandidates' => $selectedFiscalTournament && $selectedFiscalFixtureGroup
                ? $this->fiscalCandidatesFor($selectedFiscalTournament, (int) $selectedFiscalFixtureGroup['category_id'], $selectedFiscalSeries)
                : collect(),
            'fiscalAssignments' => $this->fiscalAssignmentsForDate($date),
            'scheduledMatches' => $this->scheduledMatchesForDate($date),
        ];
    }

    public function createNext(Season $season): Matchday
    {
        $this->ensureSeasonVisible($season);
        abort_unless($season->is_active, 422, 'Solo se pueden crear jornadas en gestiones activas.');

        return DB::transaction(function () use ($season): Matchday {
            $lastMatchday = Matchday::query()
                ->where('company_id', $season->company_id)
                ->where('season_id', $season->id)
                ->orderByDesc('number')
                ->lockForUpdate()
                ->first();
            $nextNumber = ((int) ($lastMatchday?->number ?? 0)) + 1;

            return Matchday::query()->create([
                'company_id' => $season->company_id,
                'season_id' => $season->id,
                'number' => $nextNumber,
                'name' => 'Jornada '.$nextNumber,
                'status' => 'draft',
            ]);
        });
    }

    public function addDate(Matchday $matchday, int $courtId, string $scheduledDate): MatchdayDate
    {
        $this->ensureMatchdayVisible($matchday);
        abort_unless($matchday->season?->is_active, 422, 'Solo se pueden agregar fechas en gestiones activas.');
        abort_if($matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');

        $court = Court::query()
            ->whereKey($courtId)
            ->where('company_id', $matchday->company_id)
            ->where('is_active', true)
            ->first();
        if (! $court) {
            throw ValidationException::withMessages([
                'court_id' => 'Selecciona una cancha activa de la liga.',
            ]);
        }

        $normalizedDate = Carbon::parse($scheduledDate)->format('Y-m-d');
        $duplicate = $matchday->dates()
            ->where('court_id', $court->id)
            ->whereDate('date', $normalizedDate)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'date' => 'Ya existe esa fecha con la misma cancha en esta jornada.',
            ]);
        }

        return DB::transaction(function () use ($matchday, $court, $normalizedDate): MatchdayDate {
            $this->normalizeDateOrder($matchday);

            return MatchdayDate::query()->create([
                'company_id' => $matchday->company_id,
                'matchday_id' => $matchday->id,
                'court_id' => $court->id,
                'date' => $normalizedDate,
                'sort_order' => ((int) $matchday->dates()->max('sort_order')) + 1,
                'status' => 'draft',
            ]);
        });
    }

    public function updateDate(MatchdayDate $date, int $courtId, string $scheduledDate): MatchdayDate
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday.season');
        abort_unless($date->matchday->season?->is_active, 422, 'Solo se pueden modificar fechas en gestiones activas.');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');

        $court = Court::query()
            ->whereKey($courtId)
            ->where('company_id', $date->company_id)
            ->where('is_active', true)
            ->first();
        if (! $court) {
            throw ValidationException::withMessages([
                'court_id' => 'Selecciona una cancha activa de la liga.',
            ]);
        }

        $normalizedDate = Carbon::parse($scheduledDate)->format('Y-m-d');
        $duplicate = MatchdayDate::query()
            ->where('matchday_id', $date->matchday_id)
            ->where('court_id', $court->id)
            ->whereDate('date', $normalizedDate)
            ->whereKeyNot($date->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'date' => 'Ya existe esa fecha con la misma cancha en esta jornada.',
            ]);
        }

        $date->update([
            'court_id' => $court->id,
            'date' => $normalizedDate,
        ]);

        return $date->refresh();
    }

    public function moveDate(Matchday $matchday, int $dateId, string $direction): void
    {
        $this->ensureMatchdayVisible($matchday);
        $matchday->loadMissing('season');
        abort_unless($matchday->season?->is_active, 422, 'Solo se pueden ordenar fechas en gestiones activas.');
        abort_if($matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');

        DB::transaction(function () use ($matchday, $dateId, $direction): void {
            $this->normalizeDateOrder($matchday);

            $dates = $this->orderedDatesForUpdate($matchday);
            $currentIndex = $dates->search(fn (MatchdayDate $date): bool => (int) $date->id === $dateId);

            if ($currentIndex === false) {
                throw ValidationException::withMessages([
                    'date_id' => 'La fecha seleccionada no pertenece a esta jornada.',
                ]);
            }

            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if (! $dates->has($targetIndex)) {
                return;
            }

            $current = $dates[$currentIndex];
            $target = $dates[$targetIndex];
            $currentOrder = $current->sort_order;
            $targetOrder = $target->sort_order;

            $current->update(['sort_order' => $targetOrder]);
            $target->update(['sort_order' => $currentOrder]);
        });
    }

    public function scheduleMatch(MatchdayDate $date, int $fixtureMatchId, string $scheduledTime): FixtureMatch
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday.season');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');

        return DB::transaction(function () use ($date, $fixtureMatchId, $scheduledTime): FixtureMatch {
            $match = FixtureMatch::query()
                ->with('tournament')
                ->whereKey($fixtureMatchId)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless((int) $match->company_id === (int) $date->company_id, 403);
            abort_unless((int) $match->tournament?->season_id === (int) $date->matchday->season_id, 422, 'El partido no pertenece a la gestion de la jornada.');
            abort_unless($match->matchday_date_id === null, 422, 'El partido ya esta programado en una fecha.');

            $match->update([
                'matchday_date_id' => $date->id,
                'scheduled_time' => $scheduledTime,
                'status' => 'scheduled',
            ]);

            return $match->refresh();
        });
    }

    public function updateScheduledTime(MatchdayDate $date, FixtureMatch $match, string $scheduledTime): FixtureMatch
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');
        abort_unless((int) $match->company_id === (int) $date->company_id, 403);
        abort_unless((int) $match->matchday_date_id === (int) $date->id, 422, 'El partido no esta programado en esta fecha.');

        $match->update([
            'scheduled_time' => $scheduledTime,
        ]);

        return $match->refresh();
    }

    public function addFiscal(MatchdayDate $date, int $teamId, string $startTime, string $endTime, ?int $tournamentId = null, ?int $categoryId = null, ?string $series = null): MatchdayDateFiscal
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday.season');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');

        $registrationQuery = TournamentRegistration::query()
            ->where('company_id', $date->company_id)
            ->where('team_id', $teamId)
            ->whereNull('deleted_at')
            ->whereHas('tournament', fn ($query) => $query->where('season_id', $date->matchday->season_id));

        if ($tournamentId && $categoryId && $series) {
            $registrationQuery
                ->where('tournament_id', $tournamentId)
                ->where('category_id', $categoryId)
                ->where('series', $series);
        }

        $teamRegisteredInSeason = $registrationQuery->exists();

        if (! $teamRegisteredInSeason) {
            throw ValidationException::withMessages([
                'team_id' => 'Selecciona un equipo registrado en la gestion de la jornada.',
            ]);
        }

        $overlap = $date->fiscals()
            ->where(fn ($query) => $query
                ->where('start_time', '<', $endTime)
                ->where('end_time', '>', $startTime))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_time' => 'Ya existe una fiscalia asignada en ese rango horario.',
            ]);
        }

        return MatchdayDateFiscal::query()->create([
            'company_id' => $date->company_id,
            'matchday_date_id' => $date->id,
            'team_id' => $teamId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    public function deleteFiscal(MatchdayDate $date, MatchdayDateFiscal $fiscal): void
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');
        abort_unless((int) $fiscal->matchday_date_id === (int) $date->id, 404);

        $fiscal->delete();
    }

    public function fiscalFilterOptions(MatchdayDate $date, ?int $tournamentId, ?string $fixtureGroup): array
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday.season');

        $tournaments = $this->fixtureTournamentsForSeason($date->matchday->season);
        $tournament = $tournaments->firstWhere('id', $tournamentId);

        if (! $tournament) {
            return [
                'fixture_groups' => [],
                'candidates' => [],
            ];
        }

        $fixtureGroups = $this->fixtureGroupsForTournament($tournament);
        [$categoryId, $series] = $this->selectedFixtureGroup(['fixture_group' => $fixtureGroup]);
        $selectedGroup = $fixtureGroups->firstWhere('value', $categoryId.'|'.$series);

        return [
            'fixture_groups' => $fixtureGroups,
            'candidates' => $selectedGroup
                ? $this->fiscalCandidatesFor($tournament, (int) $selectedGroup['category_id'], $series)
                : [],
        ];
    }

    public function fixtureMatchFilterOptions(MatchdayDate $date, ?int $tournamentId, ?string $fixtureGroup): array
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday.season');

        $tournaments = $this->fixtureTournamentsForSeason($date->matchday->season);
        $tournament = $tournaments->firstWhere('id', $tournamentId);

        if (! $tournament) {
            return [
                'fixture_groups' => [],
                'selected_tournament_id' => null,
                'selected_fixture_group' => '',
                'selected_category_id' => null,
                'selected_series' => '',
                'available_matches' => collect(),
            ];
        }

        $fixtureGroups = $this->fixtureGroupsForTournament($tournament);
        [$categoryId, $series] = $this->selectedFixtureGroup(['fixture_group' => $fixtureGroup]);
        $selectedGroup = $fixtureGroups->firstWhere('value', $categoryId.'|'.$series);

        return [
            'fixture_groups' => $fixtureGroups,
            'selected_tournament_id' => $tournament->id,
            'selected_fixture_group' => $selectedGroup['value'] ?? '',
            'selected_category_id' => $selectedGroup['category_id'] ?? null,
            'selected_series' => $selectedGroup ? $series : '',
            'available_matches' => $selectedGroup
                ? $this->availableMatchesFor($tournament, (int) $selectedGroup['category_id'], $series)
                : collect(),
        ];
    }

    public function unscheduleMatch(MatchdayDate $date, FixtureMatch $match): FixtureMatch
    {
        $this->ensureDateVisible($date);
        $date->loadMissing('matchday');
        abort_if($date->matchday->status === 'finalized', 422, 'La jornada ya fue finalizada y no se puede modificar.');
        abort_unless((int) $match->company_id === (int) $date->company_id, 403);
        abort_unless((int) $match->matchday_date_id === (int) $date->id, 422, 'El partido no esta programado en esta fecha.');

        $match->update([
            'matchday_date_id' => null,
            'scheduled_time' => null,
            'status' => 'pending_schedule',
        ]);

        return $match->refresh();
    }

    public function finish(Matchday $matchday): Matchday
    {
        $this->ensureMatchdayVisible($matchday);
        abort_if($matchday->status === 'finalized', 422, 'La jornada ya esta finalizada.');

        $matchday->loadMissing(['dates.fiscals', 'dates.fixtureMatches']);

        if ($matchday->dates->isEmpty()) {
            throw ValidationException::withMessages([
                'finalizar' => 'Agrega al menos una fecha antes de finalizar la jornada.',
            ]);
        }

        $datesWithoutMatches = $matchday->dates->filter(fn (MatchdayDate $date): bool => $date->fixtureMatches
            ->where('status', 'scheduled')
            ->isEmpty());

        if ($datesWithoutMatches->isNotEmpty()) {
            throw ValidationException::withMessages([
                'partidos' => 'Todas las fechas de la jornada deben tener al menos 1 partido programado.',
            ]);
        }

        $datesWithoutFiscals = $matchday->dates->filter(fn (MatchdayDate $date): bool => $date->fiscals->isEmpty());

        if ($datesWithoutFiscals->isNotEmpty()) {
            throw ValidationException::withMessages([
                'fiscales' => 'Todas las fechas de la jornada deben tener al menos 1 fiscalia asignada.',
            ]);
        }

        $missingFiscals = $matchday->dates->contains(fn (MatchdayDate $date): bool => $date->fixtureMatches
            ->where('status', 'scheduled')
            ->contains(fn (FixtureMatch $match): bool => ! $this->fiscalForScheduledMatch($date, $match)));

        if ($missingFiscals) {
            throw ValidationException::withMessages([
                'fiscales' => 'Todos los partidos programados deben tener fiscal de turno y horario de fiscalia.',
            ]);
        }

        $matchday->update([
            'status' => 'finalized',
        ]);

        return $matchday->refresh();
    }

    public function ensureSeasonVisible(Season $season): void
    {
        abort_unless(CompanyContext::belongsToUser($season->company_id, auth()->user()), 403);
    }

    public function ensureMatchdayVisible(Matchday $matchday): void
    {
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
    }

    public function ensureDateVisible(MatchdayDate $date): void
    {
        abort_unless(CompanyContext::belongsToUser($date->company_id, auth()->user()), 403);
    }

    private function fixtureTournamentsForSeason(Season $season): Collection
    {
        $tournamentIds = FixtureGeneration::query()
            ->where('company_id', $season->company_id)
            ->where('status', 'active')
            ->whereHas('tournament', fn ($query) => $query->where('season_id', $season->id))
            ->pluck('tournament_id')
            ->unique();

        return Tournament::query()
            ->whereIn('id', $tournamentIds)
            ->with('division')
            ->orderBy('name')
            ->get();
    }

    private function normalizeDateOrder(Matchday $matchday): void
    {
        $dates = $this->orderedDatesForUpdate($matchday);

        $dates->values()->each(function (MatchdayDate $date, int $index): void {
            $expectedOrder = $index + 1;

            if ((int) $date->sort_order !== $expectedOrder) {
                $date->update(['sort_order' => $expectedOrder]);
            }
        });
    }

    private function orderedDatesForUpdate(Matchday $matchday): Collection
    {
        return $matchday->dates()
            ->lockForUpdate()
            ->orderByRaw('COALESCE(sort_order, 2147483647)')
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    private function selectedFixtureGroup(array $filters, string $groupKey = 'fixture_group', string $categoryKey = 'category_id', string $seriesKey = 'series'): array
    {
        $fixtureGroup = (string) ($filters[$groupKey] ?? '');

        if (str_contains($fixtureGroup, '|')) {
            [$categoryId, $series] = explode('|', $fixtureGroup, 2);

            return [(int) $categoryId, $series];
        }

        return [
            (int) ($filters[$categoryKey] ?? 0),
            (string) ($filters[$seriesKey] ?? ''),
        ];
    }

    private function fixtureGroupsForTournament(Tournament $tournament): SupportCollection
    {
        $seriesGroups = FixtureMatch::query()
            ->with('category')
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('series')
            ->whereHas('fixtureGeneration', fn ($query) => $query->where('status', 'active'))
            ->get(['category_id', 'series'])
            ->unique(fn (FixtureMatch $match): string => $match->category_id.'|'.$match->series)
            ->map(fn (FixtureMatch $match): array => [
                'value' => $match->category_id.'|'.$match->series,
                'category_id' => (int) $match->category_id,
                'series' => $match->series,
                'label' => ($match->category?->name ?? 'Sin categoria').' - '.(TournamentRegistration::SERIES[$match->series] ?? str($match->series)->headline()->toString()),
            ])
            ->sortBy('label')
            ->values();

        $secondPhaseGroups = FixtureMatch::query()
            ->with('category')
            ->where('tournament_id', $tournament->id)
            ->whereNull('series')
            ->where('phase', '!=', 'group')
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->whereHas('fixtureGeneration', fn ($query) => $query->where('status', 'active'))
            ->get(['category_id'])
            ->unique(fn (FixtureMatch $match): int => (int) $match->category_id)
            ->map(fn (FixtureMatch $match): array => [
                'value' => $match->category_id.'|'.self::SECOND_PHASE_GROUP,
                'category_id' => (int) $match->category_id,
                'series' => self::SECOND_PHASE_GROUP,
                'label' => ($match->category?->name ?? 'Sin categoria').' - Segunda fase',
            ]);

        return $seriesGroups
            ->merge($secondPhaseGroups)
            ->sortBy('label')
            ->values();
    }

    private function availableMatchesFor(Tournament $tournament, int $categoryId, string $series): LengthAwarePaginator
    {
        return FixtureMatch::query()
            ->with(['homeTeam', 'awayTeam', 'tournament', 'category'])
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->when(
                $series === self::SECOND_PHASE_GROUP,
                fn ($query) => $query
                    ->whereNull('series')
                    ->where('phase', '!=', 'group')
                    ->whereNotNull('home_team_id')
                    ->whereNotNull('away_team_id'),
                fn ($query) => $query->where('series', $series)
            )
            ->whereNull('matchday_date_id')
            ->where('status', 'pending_schedule')
            ->whereHas('fixtureGeneration', fn ($query) => $query->where('status', 'active'))
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->paginate(10, ['*'], 'fixture_page')
            ->withQueryString();
    }

    private function fiscalCandidatesFor(Tournament $tournament, int $categoryId, string $series): SupportCollection
    {
        $counts = MatchdayDateFiscal::query()
            ->where('company_id', $tournament->company_id)
            ->whereHas('matchdayDate.matchday', fn ($query) => $query->where('season_id', $tournament->season_id))
            ->selectRaw('team_id, COUNT(*) as aggregate')
            ->groupBy('team_id')
            ->pluck('aggregate', 'team_id');

        $fixtureOrder = [];
        FixtureMatch::query()
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->when(
                $series === self::SECOND_PHASE_GROUP,
                fn ($query) => $query->whereNull('series')->where('phase', '!=', 'group'),
                fn ($query) => $query->where('series', $series)
            )
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get(['home_registration_id', 'away_registration_id'])
            ->each(function (FixtureMatch $match) use (&$fixtureOrder): void {
                foreach ([$match->home_registration_id, $match->away_registration_id] as $registrationId) {
                    if ($registrationId && ! array_key_exists($registrationId, $fixtureOrder)) {
                        $fixtureOrder[$registrationId] = count($fixtureOrder) + 1;
                    }
                }
            });

        return TournamentRegistration::query()
            ->with('team')
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->when($series !== self::SECOND_PHASE_GROUP, fn ($query) => $query->where('series', $series))
            ->whereNull('deleted_at')
            ->get()
            ->map(fn (TournamentRegistration $registration): array => [
                'team_id' => (int) $registration->team_id,
                'team_name' => $registration->team?->name ?? 'Equipo sin nombre',
                'team_number' => (int) $registration->team_number,
                'fixture_order' => $fixtureOrder[$registration->id] ?? PHP_INT_MAX,
                'fiscal_count' => (int) ($counts[$registration->team_id] ?? 0),
            ])
            ->sortBy([
                ['fiscal_count', 'asc'],
                ['fixture_order', 'asc'],
                ['team_number', 'asc'],
            ])
            ->values();
    }

    private function scheduledMatchesForDate(MatchdayDate $date): Collection
    {
        return $date->fixtureMatches()
            ->with(['tournament', 'category', 'homeTeam', 'awayTeam'])
            ->orderBy('scheduled_time')
            ->orderBy('match_number')
            ->get();
    }

    private function fiscalAssignmentsForDate(MatchdayDate $date): Collection
    {
        return $date->fiscals()
            ->with('team')
            ->orderBy('start_time')
            ->orderBy('end_time')
            ->get();
    }

    public function fiscalForScheduledMatch(?MatchdayDate $date, FixtureMatch $match): ?MatchdayDateFiscal
    {
        if (! $date || ! $match->scheduled_time) {
            return null;
        }

        $fiscals = $date->relationLoaded('fiscals') ? $date->fiscals : $this->fiscalAssignmentsForDate($date);
        $time = Carbon::parse($match->scheduled_time)->format('H:i:s');

        return $fiscals->first(fn (MatchdayDateFiscal $fiscal): bool => $fiscal->start_time <= $time && $fiscal->end_time > $time);
    }
}
