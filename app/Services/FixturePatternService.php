<?php

namespace App\Services;

use Illuminate\Support\Collection;

class FixturePatternService
{
    public function all(int $from = 3, int $to = 12): Collection
    {
        return collect(range($from, $to))
            ->map(fn (int $teamCount): array => [
                'team_count' => $teamCount,
                'has_bye' => $teamCount % 2 !== 0,
                'rounds' => $this->roundRobinRounds($teamCount),
            ]);
    }

    private function roundRobinRounds(int $teamCount): array
    {
        $participants = range(1, $teamCount);

        if ($teamCount % 2 !== 0) {
            $participants[] = null;
        }

        $rounds = [];
        $count = count($participants);

        for ($round = 0; $round < $count - 1; $round++) {
            $pairs = [];
            $bye = null;

            for ($i = 0; $i < $count / 2; $i++) {
                $home = $participants[$i];
                $away = $participants[$count - 1 - $i];

                if ($home === null || $away === null) {
                    $bye = $home ?? $away;

                    continue;
                }

                $pairs[] = $round % 2 === 0
                    ? ['home' => $this->teamName($home), 'away' => $this->teamName($away)]
                    : ['home' => $this->teamName($away), 'away' => $this->teamName($home)];
            }

            $rounds[] = [
                'number' => $round + 1,
                'pairs' => $pairs,
                'bye' => $bye ? $this->teamName($bye) : null,
            ];

            $fixed = array_shift($participants);
            $last = array_pop($participants);
            array_unshift($participants, $fixed);
            array_splice($participants, 1, 0, [$last]);
        }

        return $rounds;
    }

    private function teamName(int $number): string
    {
        return '('.$number.')';
    }
}
