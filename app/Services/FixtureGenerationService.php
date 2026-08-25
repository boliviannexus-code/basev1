<?php

namespace App\Services;

use App\Models\DivisionCategory;
use App\Models\FixtureGeneration;
use App\Models\FixtureMatch;
use App\Models\Tournament;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FixtureGenerationService
{
    public function __construct(
        private readonly FixtureSetupService $setup,
        private readonly StandingsService $standings
    ) {}

    public function generate(Tournament $tournament, DivisionCategory $category, array $config): FixtureGeneration
    {
        $this->setup->ensureVisible($tournament);

        $series = $this->setup->seriesFor($tournament, $category);
        $this->validateFirstPhaseConfig($series);
        $this->ensureNoActiveGeneration($tournament, $category);

        return DB::transaction(function () use ($tournament, $category, $series, $config): FixtureGeneration {
            FixtureGeneration::query()
                ->where('tournament_id', $tournament->id)
                ->where('category_id', $category->id)
                ->where('status', 'active')
                ->update(['status' => 'superseded']);

            $generation = FixtureGeneration::query()->create([
                'company_id' => $tournament->company_id,
                'tournament_id' => $tournament->id,
                'category_id' => $category->id,
                'generated_by' => auth()->id(),
                'status' => 'active',
                'config' => $this->normalizedConfig($config),
                'generated_at' => now(),
            ]);

            $matchNumber = 1;
            $this->generateGroupPhase($generation, $tournament, $category, $series, (int) $config['first_phase_rounds'], $matchNumber);
            $generation->update([
                'matches_count' => FixtureMatch::query()->where('fixture_generation_id', $generation->id)->count(),
            ]);

            return $generation->refresh();
        });
    }

    public function deleteCompletely(FixtureGeneration $generation): void
    {
        $this->setup->ensureGenerationVisible($generation);

        DB::transaction(function () use ($generation): void {
            $lockedGeneration = FixtureGeneration::query()
                ->lockForUpdate()
                ->findOrFail($generation->id);

            $lockedGeneration->delete();
        });
    }

    public function generateSecondPhase(FixtureGeneration $generation, array $config): FixtureGeneration
    {
        $this->setup->ensureGenerationVisible($generation);
        $generation->loadMissing(['tournament', 'category']);

        if ($generation->status !== 'active') {
            throw ValidationException::withMessages([
                'fixture' => 'Solo se puede definir la segunda fase del fixture activo.',
            ]);
        }

        if ($this->secondPhaseConfigured($generation)) {
            throw ValidationException::withMessages([
                'fixture' => 'La segunda fase de este fixture ya fue definida.',
            ]);
        }

        $series = $this->setup->seriesFor($generation->tournament, $generation->category);
        $this->validateSecondPhaseConfig($series, $config);

        return DB::transaction(function () use ($generation, $series, $config): FixtureGeneration {
            $generation = FixtureGeneration::query()->lockForUpdate()->findOrFail($generation->id);

            if ($generation->status !== 'active') {
                throw ValidationException::withMessages([
                    'fixture' => 'Solo se puede definir la segunda fase del fixture activo.',
                ]);
            }

            if ($this->secondPhaseConfigured($generation)) {
                throw ValidationException::withMessages([
                    'fixture' => 'La segunda fase de este fixture ya fue definida.',
                ]);
            }

            $generation->loadMissing(['tournament', 'category']);
            $matchNumber = ((int) FixtureMatch::query()
                ->where('fixture_generation_id', $generation->id)
                ->max('match_number')) + 1;

            $this->appendSecondPhase(
                $generation,
                $generation->tournament,
                $generation->category,
                $series,
                $config,
                $matchNumber
            );

            $generation->update([
                'config' => array_merge($generation->config, $this->normalizedSecondPhaseConfig($config), [
                    'second_phase_configured_at' => now()->toDateTimeString(),
                ]),
                'matches_count' => FixtureMatch::query()->where('fixture_generation_id', $generation->id)->count(),
            ]);

            return $generation->refresh();
        });
    }

    public function resolveSeeds(FixtureGeneration $generation): int
    {
        $this->setup->ensureGenerationVisible($generation);
        $generation->loadMissing(['tournament', 'category']);

        if (($generation->config['second_phase_mode'] ?? 'accumulative') === 'accumulative') {
            throw ValidationException::withMessages([
                'fixture' => 'Este campeonato es acumulativo y no tiene segunda fase para resolver.',
            ]);
        }

        if ($generation->config['seeds_resolved_at'] ?? null) {
            throw ValidationException::withMessages([
                'fixture' => 'Las semillas de este fixture ya fueron resueltas.',
            ]);
        }

        if (! $this->firstPhaseComplete($generation)) {
            throw ValidationException::withMessages([
                'fixture' => 'Para resolver semillas primero deben finalizar todos los partidos de la primera fase.',
            ]);
        }

        return DB::transaction(function () use ($generation): int {
            $seedMap = $this->seedMap($generation);
            $resolved = 0;

            FixtureMatch::query()
                ->where('fixture_generation_id', $generation->id)
                ->where('phase', '!=', 'group')
                ->orderBy('stage_order')
                ->orderBy('match_number')
                ->get()
                ->each(function (FixtureMatch $match) use ($seedMap, &$resolved): void {
                    $data = [];

                    if ($match->home_seed && $seedMap->has($match->home_seed)) {
                        $seed = $seedMap->get($match->home_seed);
                        $data['home_registration_id'] = $seed['registration_id'];
                        $data['home_team_id'] = $seed['team_id'];
                        $resolved++;
                    }

                    if ($match->away_seed && $seedMap->has($match->away_seed)) {
                        $seed = $seedMap->get($match->away_seed);
                        $data['away_registration_id'] = $seed['registration_id'];
                        $data['away_team_id'] = $seed['team_id'];
                        $resolved++;
                    }

                    if ($data !== []) {
                        $match->update($data);
                    }
                });

            $config = $generation->config;
            $config['seeds_resolved_at'] = now()->toDateTimeString();
            $generation->update(['config' => $config]);

            return $resolved;
        });
    }

    public function firstPhaseComplete(FixtureGeneration $generation): bool
    {
        $groupMatches = FixtureMatch::query()
            ->where('fixture_generation_id', $generation->id)
            ->where('phase', 'group');

        if (! $groupMatches->exists()) {
            return false;
        }

        return ! (clone $groupMatches)
            ->whereDoesntHave('report', fn ($query) => $query->whereIn('status', ['completed', 'walkover']))
            ->exists();
    }

    private function generateGroupPhase(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, Collection $series, int $rounds, int &$matchNumber): void
    {
        foreach ($series as $serie) {
            $registrations = $serie['registrations']->values();
            $pairRounds = $this->roundRobinRounds($registrations);
            $baseRoundCount = count($pairRounds);

            for ($leg = 1; $leg <= $rounds; $leg++) {
                foreach ($pairRounds as $roundIndex => $pairs) {
                    foreach ($pairs as $pair) {
                        [$home, $away] = $leg === 1 ? $pair : [$pair[1], $pair[0]];

                        $this->createMatch($generation, $tournament, $category, [
                            'phase' => 'group',
                            'stage' => 'Primera fase',
                            'stage_order' => 1,
                            'series' => $serie['key'],
                            'round_number' => $roundIndex + 1 + (($leg - 1) * $baseRoundCount),
                            'match_number' => $matchNumber++,
                            'leg_number' => $leg,
                            'home_registration_id' => $home->id,
                            'away_registration_id' => $away->id,
                            'home_team_id' => $home->team_id,
                            'away_team_id' => $away->team_id,
                        ]);
                    }
                }
            }
        }
    }

    private function ensureNoActiveGeneration(Tournament $tournament, DivisionCategory $category): void
    {
        $exists = FixtureGeneration::query()
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $category->id)
            ->where('status', 'active')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'fixture' => 'Esta categoria ya tiene un fixture generado. No se puede volver a generar.',
            ]);
        }
    }

    private function seedMap(FixtureGeneration $generation): Collection
    {
        $tournament = $generation->tournament;
        $category = $generation->category;
        $series = $this->setup->seriesFor($tournament, $category);
        $qualifiersPerSeries = (int) ($generation->config['qualifiers_per_series'] ?? 0);
        $map = collect();
        $nextRankCandidates = collect();

        foreach ($series as $serie) {
            $standings = $this->standings->table($tournament, $category->id, $serie['key']);

            for ($rank = 1; $rank <= $qualifiersPerSeries; $rank++) {
                $row = $standings->get($rank - 1);

                if ($row) {
                    $map->put($rank.' '.$serie['label'], $row);
                }
            }

            $nextRank = $standings->get($qualifiersPerSeries);

            if ($nextRank) {
                $nextRankCandidates->push($nextRank + ['series_label' => $serie['label']]);
            }
        }

        $this->addBestExtraSeeds($generation, $map, $nextRankCandidates);

        return $map;
    }

    private function addBestExtraSeeds(FixtureGeneration $generation, Collection $map, Collection $candidates): void
    {
        if (($generation->config['second_phase_mode'] ?? '') !== 'knockout') {
            return;
        }

        $baseCount = $map->count();
        $missing = $this->bracketTarget($baseCount) - $baseCount;

        if ($missing <= 0) {
            return;
        }

        $candidates
            ->sortBy([
                ['points', 'desc'],
                ['goal_difference', 'desc'],
                ['goals_for', 'desc'],
                ['team_name', 'asc'],
            ])
            ->values()
            ->take($missing)
            ->each(fn (array $row, int $index) => $map->put('Mejor tercero '.($index + 1), $row));
    }

    private function appendSecondPhase(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, Collection $series, array $config, int &$matchNumber): void
    {
        $mode = $config['second_phase_mode'];

        if ($mode === 'accumulative') {
            return;
        }

        $qualifiersPerSeries = (int) $config['qualifiers_per_series'];
        $qualifiedSeeds = $this->qualifiedSeeds($series, $qualifiersPerSeries);

        if ($mode === 'league') {
            $this->generateLeaguePhase($generation, $tournament, $category, $qualifiedSeeds, $config, $matchNumber);

            return;
        }

        $this->generateKnockoutPhase($generation, $tournament, $category, $qualifiedSeeds, $config, $matchNumber);
    }

    private function generateLeaguePhase(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, array $seeds, array $config, int &$matchNumber): void
    {
        $pairRounds = $this->roundRobinRounds(collect($seeds));
        $baseRoundCount = count($pairRounds);
        $leagueRounds = (int) ($config['league_rounds'] ?? 1);

        for ($leg = 1; $leg <= $leagueRounds; $leg++) {
            foreach ($pairRounds as $roundIndex => $pairs) {
                foreach ($pairs as $pair) {
                    [$home, $away] = $leg === 1 ? $pair : [$pair[1], $pair[0]];

                    $this->createMatch($generation, $tournament, $category, [
                        'phase' => 'league',
                        'stage' => 'Liguilla',
                        'stage_order' => 20,
                        'round_number' => $roundIndex + 1 + (($leg - 1) * $baseRoundCount),
                        'match_number' => $matchNumber++,
                        'leg_number' => $leg,
                        'home_seed' => $home,
                        'away_seed' => $away,
                    ]);
                }
            }
        }

        if (($config['league_champion_rule'] ?? 'table') === 'top_two_final') {
            $this->createFinalFromSeeds($generation, $tournament, $category, 'Final de liguilla', '1 Liguilla', '2 Liguilla', $matchNumber, 30);
        }

        if (($config['league_champion_rule'] ?? 'table') === 'semifinal_final') {
            $this->createMatch($generation, $tournament, $category, [
                'phase' => 'league_playoff',
                'stage' => 'Semifinal de liguilla',
                'stage_order' => 30,
                'match_number' => $matchNumber++,
                'tie_number' => 1,
                'home_seed' => '1 Liguilla',
                'away_seed' => '4 Liguilla',
            ]);
            $this->createMatch($generation, $tournament, $category, [
                'phase' => 'league_playoff',
                'stage' => 'Semifinal de liguilla',
                'stage_order' => 30,
                'match_number' => $matchNumber++,
                'tie_number' => 2,
                'home_seed' => '2 Liguilla',
                'away_seed' => '3 Liguilla',
            ]);
            $this->createFinalFromSeeds($generation, $tournament, $category, 'Final de liguilla', 'Ganador Semifinal de liguilla 1', 'Ganador Semifinal de liguilla 2', $matchNumber, 31);
        }
    }

    private function generateKnockoutPhase(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, array $seeds, array $config, int &$matchNumber): void
    {
        $target = $this->bracketTarget(count($seeds));
        $seedList = $this->completeSeeds($seeds, $target);
        $stages = $this->bracketStages($target);
        $previousStage = null;

        foreach ($stages as $stageIndex => $stage) {
            $ties = $stage['size'] / 2;
            $legMode = $config['stage_leg_modes'][$stage['key']] ?? $stage['default_leg_mode'];
            $legs = $legMode === 'double' ? 2 : 1;

            for ($tie = 1; $tie <= $ties; $tie++) {
                if ($stageIndex === 0) {
                    $homeSeed = $seedList[$tie - 1] ?? null;
                    $awaySeed = $seedList[$stage['size'] - $tie] ?? null;
                } else {
                    $homeSeed = 'Ganador '.$previousStage.' '.(($tie * 2) - 1);
                    $awaySeed = 'Ganador '.$previousStage.' '.($tie * 2);
                }

                for ($leg = 1; $leg <= $legs; $leg++) {
                    $this->createMatch($generation, $tournament, $category, [
                        'phase' => 'knockout',
                        'stage' => $stage['label'],
                        'stage_order' => 10 + $stageIndex,
                        'match_number' => $matchNumber++,
                        'tie_number' => $tie,
                        'leg_number' => $leg,
                        'home_seed' => $leg === 1 ? $homeSeed : $awaySeed,
                        'away_seed' => $leg === 1 ? $awaySeed : $homeSeed,
                    ]);
                }
            }

            $previousStage = $stage['label'];
        }

        if (($config['third_place'] ?? false) && $target >= 4) {
            $this->createMatch($generation, $tournament, $category, [
                'phase' => 'knockout',
                'stage' => 'Tercer lugar',
                'stage_order' => 99,
                'match_number' => $matchNumber++,
                'home_seed' => 'Perdedor Semifinal 1',
                'away_seed' => 'Perdedor Semifinal 2',
            ]);
        }
    }

    private function createFinalFromSeeds(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, string $stage, string $homeSeed, string $awaySeed, int &$matchNumber, int $stageOrder): void
    {
        $this->createMatch($generation, $tournament, $category, [
            'phase' => 'league_playoff',
            'stage' => $stage,
            'stage_order' => $stageOrder,
            'match_number' => $matchNumber++,
            'home_seed' => $homeSeed,
            'away_seed' => $awaySeed,
        ]);
    }

    private function createMatch(FixtureGeneration $generation, Tournament $tournament, DivisionCategory $category, array $data): FixtureMatch
    {
        return FixtureMatch::query()->create(array_merge([
            'company_id' => $tournament->company_id,
            'fixture_generation_id' => $generation->id,
            'tournament_id' => $tournament->id,
            'division_id' => $tournament->division_id,
            'category_id' => $category->id,
            'status' => 'pending_schedule',
        ], $data));
    }

    private function roundRobinRounds(Collection $items): array
    {
        $participants = $items->values()->all();

        if (count($participants) < 2) {
            return [];
        }

        if (count($participants) % 2 !== 0) {
            $participants[] = null;
        }

        $rounds = [];
        $count = count($participants);

        for ($round = 0; $round < $count - 1; $round++) {
            $pairs = [];

            for ($i = 0; $i < $count / 2; $i++) {
                $home = $participants[$i];
                $away = $participants[$count - 1 - $i];

                if ($home !== null && $away !== null) {
                    $pairs[] = $round % 2 === 0 ? [$home, $away] : [$away, $home];
                }
            }

            $rounds[] = $pairs;
            $fixed = array_shift($participants);
            $last = array_pop($participants);
            array_unshift($participants, $fixed);
            array_splice($participants, 1, 0, [$last]);
        }

        return $rounds;
    }

    private function qualifiedSeeds(Collection $series, int $qualifiersPerSeries): array
    {
        $seeds = [];

        for ($rank = 1; $rank <= $qualifiersPerSeries; $rank++) {
            foreach ($series as $serie) {
                $seeds[] = $rank.' '.$serie['label'];
            }
        }

        return $seeds;
    }

    private function completeSeeds(array $seeds, int $target): array
    {
        $missing = $target - count($seeds);

        for ($i = 1; $i <= $missing; $i++) {
            $seeds[] = 'Mejor tercero '.$i;
        }

        return $seeds;
    }

    private function bracketTarget(int $teams): int
    {
        foreach ([2, 4, 8, 16, 32] as $target) {
            if ($teams <= $target) {
                return $target;
            }
        }

        throw ValidationException::withMessages([
            'qualifiers_per_series' => 'Las llaves soportan hasta 32 clasificados. Usa liguilla o reduce clasificados.',
        ]);
    }

    private function bracketStages(int $target): array
    {
        return collect([
            ['key' => 'round_of_32', 'size' => 32, 'label' => '16avos de final', 'default_leg_mode' => 'double'],
            ['key' => 'round_of_16', 'size' => 16, 'label' => 'Octavos de final', 'default_leg_mode' => 'double'],
            ['key' => 'quarterfinal', 'size' => 8, 'label' => 'Cuartos de final', 'default_leg_mode' => 'double'],
            ['key' => 'semifinal', 'size' => 4, 'label' => 'Semifinal', 'default_leg_mode' => 'single'],
            ['key' => 'final', 'size' => 2, 'label' => 'Final', 'default_leg_mode' => 'single'],
        ])->filter(fn (array $stage): bool => $target >= $stage['size'])->values()->all();
    }

    private function validateFirstPhaseConfig(Collection $series): void
    {
        if ($series->isEmpty()) {
            throw ValidationException::withMessages(['series' => 'No hay series con equipos para generar fixture.']);
        }
    }

    private function validateSecondPhaseConfig(Collection $series, array $config): void
    {
        $this->validateFirstPhaseConfig($series);
        $minTeamCount = (int) ($series->min('team_count') ?? 0);
        $qualifiers = (int) $config['qualifiers_per_series'];

        if ($qualifiers > $minTeamCount) {
            throw ValidationException::withMessages([
                'qualifiers_per_series' => 'Los clasificados por serie no pueden superar la cantidad de equipos de la serie mas pequena.',
            ]);
        }

        if ($config['second_phase_mode'] !== 'accumulative' && $qualifiers < 1) {
            throw ValidationException::withMessages([
                'qualifiers_per_series' => 'Debes clasificar al menos un equipo para generar segunda fase.',
            ]);
        }
    }

    private function normalizedConfig(array $config): array
    {
        return [
            'first_phase_rounds' => (int) $config['first_phase_rounds'],
            'second_phase_status' => 'pending',
        ];
    }

    private function normalizedSecondPhaseConfig(array $config): array
    {
        return [
            'second_phase_status' => 'configured',
            'qualifiers_per_series' => (int) $config['qualifiers_per_series'],
            'second_phase_mode' => $config['second_phase_mode'],
            'fill_rule' => $config['fill_rule'] ?? 'best_thirds',
            'third_place' => (bool) ($config['third_place'] ?? false),
            'league_rounds' => (int) ($config['league_rounds'] ?? 1),
            'league_champion_rule' => $config['league_champion_rule'] ?? 'table',
            'stage_leg_modes' => $config['stage_leg_modes'] ?? [],
        ];
    }

    private function secondPhaseConfigured(FixtureGeneration $generation): bool
    {
        return ($generation->config['second_phase_status'] ?? null) === 'configured'
            || array_key_exists('second_phase_mode', $generation->config);
    }
}
