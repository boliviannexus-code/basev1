<?php

namespace App\Services;

use App\Models\FixtureMatch;
use App\Models\MatchReportPlayer;
use App\Models\StandingAdjustment;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use Illuminate\Support\Collection;

class StandingsService
{
    public function table(Tournament $tournament, int $categoryId, string $series): Collection
    {
        $rows = $this->baseRows($tournament, $categoryId, $series);

        $matches = FixtureMatch::query()
            ->with(['report.players', 'homeTeam', 'awayTeam'])
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->whereHas('report', fn ($query) => $query->whereIn('status', ['completed', 'walkover']))
            ->orderBy('round_number')
            ->orderBy('match_number')
            ->get();

        foreach ($matches as $match) {
            $report = $match->report;

            if (! $report || ! $match->home_team_id || ! $match->away_team_id) {
                continue;
            }

            $this->applySide(
                $rows,
                $match->home_team_id,
                $report->home_score,
                $report->away_score,
                $report->home_points,
                $this->outcomeFor($report->home_score, $report->away_score, $report->status, $report->wo_side, 'home'),
                $report->status === 'walkover' && in_array($report->wo_side, ['home', 'double'], true),
                $report->players->where('team_side', 'home')
            );

            $this->applySide(
                $rows,
                $match->away_team_id,
                $report->away_score,
                $report->home_score,
                $report->away_points,
                $this->outcomeFor($report->away_score, $report->home_score, $report->status, $report->wo_side, 'away'),
                $report->status === 'walkover' && in_array($report->wo_side, ['away', 'double'], true),
                $report->players->where('team_side', 'away')
            );
        }

        $this->applyAdjustments($rows, $tournament, $categoryId, $series);

        return $rows
            ->values()
            ->sortBy([
                ['final_points', 'desc'],
                ['goal_difference', 'desc'],
                ['goals_for', 'desc'],
                ['team_name', 'asc'],
            ])
            ->values()
            ->map(function (array $row, int $index): array {
                $row['position'] = $index + 1;

                return $row;
            });
    }

    public function topScorers(Tournament $tournament, int $categoryId, string $series, int $limit = 5): Collection
    {
        $teamIds = TournamentRegistration::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->where('status', 'registered')
            ->pluck('team_id');

        if ($teamIds->isEmpty()) {
            return collect();
        }

        return MatchReportPlayer::query()
            ->with(['player', 'team'])
            ->where('company_id', $tournament->company_id)
            ->whereIn('team_id', $teamIds)
            ->where('goals', '>', 0)
            ->whereHas('matchReport', function ($query) use ($tournament, $categoryId): void {
                $query
                    ->whereIn('status', ['completed', 'walkover'])
                    ->whereHas('fixtureMatch', function ($query) use ($tournament, $categoryId): void {
                        $query
                            ->where('tournament_id', $tournament->id)
                            ->where('category_id', $categoryId);
                    });
            })
            ->get()
            ->groupBy(fn (MatchReportPlayer $player): string => $player->player_id.'|'.$player->team_id)
            ->map(function (Collection $rows): array {
                /** @var MatchReportPlayer $first */
                $first = $rows->first();

                return [
                    'player_id' => $first->player_id,
                    'player_name' => $first->player?->full_name ?? 'Jugador '.$first->player_id,
                    'team_id' => $first->team_id,
                    'team_name' => $first->team?->name ?? 'Equipo '.$first->team_id,
                    'goals' => $rows->sum(fn (MatchReportPlayer $row): int => (int) $row->goals),
                ];
            })
            ->sortBy([
                ['goals', 'desc'],
                ['player_name', 'asc'],
            ])
            ->values()
            ->take($limit);
    }

    private function baseRows(Tournament $tournament, int $categoryId, string $series): Collection
    {
        return TournamentRegistration::query()
            ->with('team')
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->where('status', 'registered')
            ->orderBy('team_number')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (TournamentRegistration $registration): array => [
                $registration->team_id => [
                    'registration_id' => $registration->id,
                    'team_id' => $registration->team_id,
                    'team_name' => $registration->team?->name ?? 'Equipo '.$registration->team_id,
                    'team_number' => $registration->team_number,
                    'played' => 0,
                    'won' => 0,
                    'drawn' => 0,
                    'lost' => 0,
                    'goals_for' => 0,
                    'goals_against' => 0,
                    'goal_difference' => 0,
                    'points' => 0,
                    'walkovers' => 0,
                    'yellow_cards' => 0,
                    'red_cards' => 0,
                    'adjustment_points' => 0,
                    'final_points' => 0,
                ],
            ]);
    }

    private function applySide(
        Collection $rows,
        int $teamId,
        int $goalsFor,
        int $goalsAgainst,
        int $points,
        string $outcome,
        bool $walkover,
        Collection $players
    ): void {
        if (! $rows->has($teamId)) {
            return;
        }

        $row = $rows->get($teamId);
        $row['played']++;
        $row['goals_for'] += $goalsFor;
        $row['goals_against'] += $goalsAgainst;
        $row['goal_difference'] = $row['goals_for'] - $row['goals_against'];
        $row['points'] += $points;
        $row['final_points'] = $row['points'] + $row['adjustment_points'];
        $row['walkovers'] += $walkover ? 1 : 0;
        $row['yellow_cards'] += $players->sum(fn (MatchReportPlayer $player): int => (int) $player->yellow_cards);
        $row['red_cards'] += $players->sum(fn (MatchReportPlayer $player): int => (int) $player->red_cards);

        match ($outcome) {
            'won' => $row['won']++,
            'drawn' => $row['drawn']++,
            'lost' => $row['lost']++,
            default => null,
        };

        $rows->put($teamId, $row);
    }

    private function applyAdjustments(Collection $rows, Tournament $tournament, int $categoryId, string $series): void
    {
        $adjustments = StandingAdjustment::query()
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('category_id', $categoryId)
            ->where('series', $series)
            ->selectRaw('team_id, SUM(points_adjustment) as aggregate')
            ->groupBy('team_id')
            ->pluck('aggregate', 'team_id');

        $rows->each(function (array $row, int $teamId) use ($rows, $adjustments): void {
            $row['adjustment_points'] = (int) ($adjustments[$teamId] ?? 0);
            $row['final_points'] = $row['points'] + $row['adjustment_points'];
            $rows->put($teamId, $row);
        });
    }

    private function outcomeFor(int $ownScore, int $opponentScore, string $status, ?string $woSide, string $side): string
    {
        if ($status === 'walkover' && $woSide === 'double') {
            return 'lost';
        }

        if ($status === 'walkover') {
            return $woSide === $side ? 'lost' : 'won';
        }

        if ($ownScore === $opponentScore) {
            return 'drawn';
        }

        return $ownScore > $opponentScore ? 'won' : 'lost';
    }
}
