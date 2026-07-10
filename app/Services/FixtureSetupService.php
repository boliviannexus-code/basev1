<?php

namespace App\Services;

use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;

class FixtureSetupService
{
    public function tournaments(): Collection
    {
        return CompanyContext::scope(Tournament::query())
            ->with(['season', 'division', 'categories'])
            ->withCount(['registrations' => fn ($query) => $query->whereNull('deleted_at')])
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();
    }

    public function categoriesFor(Tournament $tournament): Collection
    {
        $this->ensureVisible($tournament);

        $categories = $tournament->categories()
            ->where('division_categories.is_active', true)
            ->orderBy('division_categories.name')
            ->get();

        $counts = TournamentRegistration::query()
            ->where('tournament_id', $tournament->id)
            ->whereNull('deleted_at')
            ->selectRaw('category_id, COUNT(*) as aggregate')
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $categories->each(function (DivisionCategory $category) use ($counts): void {
            $category->setAttribute('registrations_count', (int) ($counts[$category->id] ?? 0));
        });

        return $categories;
    }

    public function seriesFor(Tournament $tournament, DivisionCategory $category): SupportCollection
    {
        $this->ensureVisible($tournament);
        abort_unless($this->categoryBelongsToTournament($tournament, $category), 404);

        return TournamentRegistration::query()
            ->with('team')
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $category->id)
            ->whereNull('deleted_at')
            ->orderBy('series')
            ->orderBy('team_number')
            ->get()
            ->groupBy('series')
            ->map(function (SupportCollection $registrations, string $series): array {
                return [
                    'key' => $series,
                    'label' => TournamentRegistration::SERIES[$series] ?? str($series)->headline()->toString(),
                    'registrations' => $registrations,
                    'team_count' => $registrations->count(),
                ];
            })
            ->values();
    }

    public function categoryContext(Tournament $tournament, DivisionCategory $category): array
    {
        $series = $this->seriesFor($tournament, $category);
        $minTeamCount = (int) ($series->min('team_count') ?? 0);

        return [
            'tournament' => $tournament->loadMissing(['season', 'division']),
            'category' => $category,
            'series' => $series,
            'activeGeneration' => $this->activeGeneration($tournament, $category),
            'seriesCount' => $series->count(),
            'totalTeams' => $series->sum('team_count'),
            'minTeamCount' => $minTeamCount,
            'defaultQualifiersPerSeries' => min(2, $minTeamCount),
            'hasOddSeries' => $series->contains(fn (array $serie): bool => $serie['team_count'] % 2 !== 0),
            'oddSeriesLabels' => $series
                ->filter(fn (array $serie): bool => $serie['team_count'] % 2 !== 0)
                ->pluck('label')
                ->implode(', '),
        ];
    }

    public function seriesTeamContext(Tournament $tournament, DivisionCategory $category, string $series): array
    {
        $seriesData = $this->seriesFor($tournament, $category)->firstWhere('key', $series);

        abort_unless($seriesData, 404);

        return [
            'tournament' => $tournament->loadMissing(['season', 'division']),
            'category' => $category,
            'series' => $seriesData,
        ];
    }

    public function ensureVisible(Tournament $tournament): void
    {
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);
    }

    public function ensureGenerationVisible(FixtureGeneration $generation): void
    {
        abort_unless(CompanyContext::belongsToUser($generation->company_id, auth()->user()), 403);
    }

    public function activeGeneration(Tournament $tournament, DivisionCategory $category): ?FixtureGeneration
    {
        return FixtureGeneration::query()
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $category->id)
            ->where('status', 'active')
            ->latest('generated_at')
            ->first();
    }

    public function reportContext(FixtureGeneration $generation): array
    {
        $this->ensureGenerationVisible($generation);

        $generation->load([
            'company',
            'category',
            'tournament.season',
            'tournament.division',
            'matches.homeTeam',
            'matches.awayTeam',
            'matches.matchdayDate',
            'matches.report',
        ]);

        return [
            'generation' => $generation,
            'matchesByStage' => $generation->matches
                ->sortBy([['stage_order', 'asc'], ['round_number', 'asc'], ['match_number', 'asc']])
                ->groupBy(fn ($match): string => $match->phase.'|'.$match->stage),
            'firstPhasePendingCount' => $this->firstPhasePendingCount($generation),
            'hasSecondPhaseSeeds' => $generation->matches
                ->where('phase', '!=', 'group')
                ->contains(fn ($match): bool => filled($match->home_seed) || filled($match->away_seed)),
            'seedsResolved' => filled($generation->config['seeds_resolved_at'] ?? null),
        ];
    }

    private function firstPhasePendingCount(FixtureGeneration $generation): int
    {
        return $generation->matches
            ->where('phase', 'group')
            ->filter(fn ($match): bool => ! in_array($match->report?->status, ['completed', 'walkover'], true))
            ->count();
    }

    private function categoryBelongsToTournament(Tournament $tournament, DivisionCategory $category): bool
    {
        return $tournament->categories()
            ->whereKey($category->id)
            ->exists();
    }
}
