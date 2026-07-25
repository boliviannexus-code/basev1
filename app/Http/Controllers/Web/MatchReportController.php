<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\MatchReport\AddMatchReportPlayerRequest;
use App\Http\Requests\MatchReport\UpdateMatchReportPlayerStatsRequest;
use App\Http\Requests\MatchReport\UpdateMatchReportRequest;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchReport;
use App\Models\MatchReportPlayer;
use App\Models\TournamentTeamPlayer;
use App\Services\MatchControlItemService;
use App\Services\MatchReportPdfService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MatchReportController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('match-reports.view'), 403);

        $matchdays = Matchday::query()
            ->with(['season', 'dates.court'])
            ->withCount(['dates', 'fixtureMatches'])
            ->where('status', 'finalized')
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('number')
            ->paginate(15);

        return view('match-reports.index', compact('matchdays'));
    }

    public function show(Matchday $matchday): View
    {
        $this->ensureVisibleFinalizedMatchday($matchday);

        $matchday->load([
            'season',
            'dates' => fn ($query) => $query
                ->orderByRaw('COALESCE(sort_order, 2147483647)')
                ->orderBy('date')
                ->orderBy('id'),
            'dates.court',
            'dates.fixtureMatches' => fn ($query) => $query
                ->with(['homeTeam', 'awayTeam', 'category', 'tournament', 'report'])
                ->orderBy('scheduled_time')
                ->orderBy('match_number'),
        ]);

        return view('match-reports.show', compact('matchday'));
    }

    public function edit(FixtureMatch $fixtureMatch, MatchControlItemService $controlItems): View
    {
        $this->ensureVisibleFinalizedMatch($fixtureMatch);

        $fixtureMatch->load([
            'homeTeam',
            'awayTeam',
            'category',
            'tournament',
            'matchdayDate.matchday.season',
            'matchdayDate.court',
            'report',
        ]);

        $items = $controlItems->itemsForCompany($fixtureMatch->company_id);

        return view('match-reports.edit', [
            'match' => $fixtureMatch,
            'report' => $fixtureMatch->report,
            'controlItems' => $items,
            'controlValues' => $controlItems->valuesForReport($fixtureMatch->report, $items),
        ]);
    }

    public function update(UpdateMatchReportRequest $request, FixtureMatch $fixtureMatch, MatchControlItemService $controlItems): RedirectResponse
    {
        $this->ensureVisibleFinalizedMatch($fixtureMatch);

        $data = $request->validated();
        $data['company_id'] = $fixtureMatch->company_id;
        $data['fixture_match_id'] = $fixtureMatch->id;
        $items = $controlItems->itemsForCompany($fixtureMatch->company_id);
        $data['control_items'] = $controlItems->normalizeSubmitted($data['control_items'] ?? [], $items);
        $data['home_present'] = data_get($data, 'control_items.home.present', false);
        $data['away_present'] = data_get($data, 'control_items.away.present', false);
        $data['home_paid_court_fee'] = data_get($data, 'control_items.home.court_fee_paid', false);
        $data['away_paid_court_fee'] = data_get($data, 'control_items.away.court_fee_paid', false);
        $data['home_brought_ball'] = data_get($data, 'control_items.home.trajo_balon', false);
        $data['away_brought_ball'] = data_get($data, 'control_items.away.trajo_balon', false);

        foreach ([
            'home_brought_ball',
            'away_brought_ball',
            'home_present',
            'away_present',
            'home_paid_court_fee',
            'away_paid_court_fee',
        ] as $field) {
            $data[$field] = (bool) ($data[$field] ?? false);
        }

        $data = array_merge($data, $this->initialResultState($data));

        $report = MatchReport::query()->updateOrCreate(
            ['fixture_match_id' => $fixtureMatch->id],
            $data
        );

        if ($report->status === 'started') {
            return redirect()
                ->route('match-reports.matches.play', $fixtureMatch)
                ->with('success', 'Registro inicial guardado. Partido iniciado.');
        }

        return redirect()
            ->route('match-reports.matchdays.show', $fixtureMatch->matchdayDate->matchday)
            ->with('success', 'Partido registrado por W.O. Se genero la planilla.')
            ->with('report_url', route('match-reports.reports.pdf', $report));
    }

    public function play(FixtureMatch $fixtureMatch): View
    {
        $this->ensureVisibleFinalizedMatch($fixtureMatch);
        $report = $this->startedReportFor($fixtureMatch);

        return view('match-reports.play', $this->playContext($report));
    }

    public function addPlayer(AddMatchReportPlayerRequest $request, MatchReport $matchReport): JsonResponse
    {
        $this->ensureEditableReport($matchReport);
        $matchReport->loadMissing('fixtureMatch');

        $side = $request->validated('team_side');
        $teamId = $side === 'home'
            ? $matchReport->fixtureMatch->home_team_id
            : $matchReport->fixtureMatch->away_team_id;

        $habilitation = TournamentTeamPlayer::query()
            ->with('player')
            ->whereKey($request->integer('tournament_team_player_id'))
            ->where('company_id', $matchReport->company_id)
            ->where('tournament_id', $matchReport->fixtureMatch->tournament_id)
            ->where('team_id', $teamId)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->firstOrFail();

        MatchReportPlayer::query()->firstOrCreate([
            'match_report_id' => $matchReport->id,
            'tournament_team_player_id' => $habilitation->id,
        ], [
            'company_id' => $matchReport->company_id,
            'player_id' => $habilitation->player_id,
            'team_id' => $habilitation->team_id,
            'team_side' => $side,
            'jersey_number' => $request->filled('jersey_number') ? $request->integer('jersey_number') : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Jugador agregado al partido.',
        ]);
    }

    public function updatePlayerStats(UpdateMatchReportPlayerStatsRequest $request, MatchReportPlayer $player): JsonResponse
    {
        $this->ensureEditableReport($player->matchReport);

        $field = $request->validated('field');
        $delta = (int) $request->validated('delta');
        $updates = $this->statsUpdatesFor($player, $field, $delta);
        $player->update($updates);
        $this->syncScoreFromPlayers($player->matchReport);

        return response()->json([
            'success' => true,
            'message' => 'Estadistica actualizada.',
        ]);
    }

    public function finish(MatchReport $matchReport): RedirectResponse
    {
        $this->ensureEditableReport($matchReport);
        $this->syncScoreFromPlayers($matchReport);
        $matchReport->refresh();

        DB::transaction(function () use ($matchReport): void {
            $this->ensureEliminationCanBeClosed($matchReport);

            $matchReport->update(array_merge(
                $this->pointsForScore((int) $matchReport->home_score, (int) $matchReport->away_score),
                ['status' => 'completed']
            ));

            $matchReport->refresh();
            $this->advanceEliminationWinner($matchReport);
        });

        $matchReport->loadMissing('fixtureMatch.matchdayDate.matchday');

        return redirect()
            ->route('match-reports.matchdays.show', $matchReport->fixtureMatch->matchdayDate->matchday)
            ->with('success', 'Partido finalizado. Se genero la planilla.')
            ->with('report_url', route('match-reports.reports.pdf', $matchReport));
    }

    public function pdf(MatchReport $matchReport, MatchReportPdfService $reportService): Response
    {
        $this->ensurePrintableReport($matchReport);

        return $reportService->controlSheet($matchReport);
    }

    public function reopen(MatchReport $matchReport): RedirectResponse
    {
        abort_unless(auth()->user()?->can('match-reports.reopen'), 403);
        abort_unless(CompanyContext::belongsToUser($matchReport->company_id, auth()->user()), 403);
        abort_unless(in_array($matchReport->status, ['completed', 'walkover'], true), 422, 'Solo se pueden editar partidos finalizados.');

        $matchReport->loadMissing('fixtureMatch.matchdayDate.matchday');

        DB::transaction(function () use ($matchReport): void {
            if ($matchReport->status === 'completed') {
                $this->clearAdvancedSeeds($matchReport);
            }

            $matchReport->update([
                'status' => $matchReport->status === 'walkover' ? 'draft' : 'started',
            ]);
        });

        return redirect()
            ->route(
                $matchReport->status === 'draft' ? 'match-reports.matches.edit' : 'match-reports.matches.play',
                $matchReport->fixtureMatch
            )
            ->with('success', 'Partido habilitado para edicion.');
    }

    private function ensureVisibleFinalizedMatchday(Matchday $matchday): void
    {
        abort_unless(auth()->user()?->can('match-reports.view'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_unless($matchday->status === 'finalized', 404);
    }

    private function ensureVisibleFinalizedMatch(FixtureMatch $match): void
    {
        abort_unless(auth()->user()?->can('match-reports.view'), 403);
        abort_unless(CompanyContext::belongsToUser($match->company_id, auth()->user()), 403);

        $match->loadMissing('matchdayDate.matchday');

        abort_unless($match->matchdayDate?->matchday?->status === 'finalized', 404);
    }

    private function ensureEditableReport(MatchReport $report): void
    {
        abort_unless(auth()->user()?->can('match-reports.update'), 403);
        abort_unless(CompanyContext::belongsToUser($report->company_id, auth()->user()), 403);
        abort_unless($report->status === 'started', 422, 'Solo se puede editar un partido iniciado.');
    }

    private function ensurePrintableReport(MatchReport $report): void
    {
        abort_unless(auth()->user()?->can('match-reports.view'), 403);
        abort_unless(CompanyContext::belongsToUser($report->company_id, auth()->user()), 403);
        abort_unless(in_array($report->status, ['completed', 'walkover'], true), 422, 'El reporte solo esta disponible para partidos finalizados o W.O.');
    }

    private function startedReportFor(FixtureMatch $match): MatchReport
    {
        $report = $match->report()->firstOrFail();

        abort_unless($report->status === 'started', 422, 'El partido no esta iniciado.');

        return $report;
    }

    private function initialResultState(array $data): array
    {
        $homeFails = ! $data['home_present'] || ! $data['home_paid_court_fee'];
        $awayFails = ! $data['away_present'] || ! $data['away_paid_court_fee'];

        if ($homeFails && $awayFails) {
            return [
                'status' => 'walkover',
                'home_score' => 0,
                'away_score' => 0,
                'home_points' => 0,
                'away_points' => 0,
                'wo_side' => 'double',
                'wo_reason' => (! $data['home_paid_court_fee'] || ! $data['away_paid_court_fee']) ? 'court_fee' : 'absence',
            ];
        }

        if ($homeFails || $awayFails) {
            $homeWins = $awayFails;

            return [
                'status' => 'walkover',
                'home_score' => $homeWins ? 3 : 0,
                'away_score' => $homeWins ? 0 : 3,
                'home_points' => $homeWins ? 3 : 0,
                'away_points' => $homeWins ? 0 : 3,
                'wo_side' => $homeFails ? 'home' : 'away',
                'wo_reason' => (! $data[$homeFails ? 'home_paid_court_fee' : 'away_paid_court_fee']) ? 'court_fee' : 'absence',
            ];
        }

        return [
            'status' => 'started',
            'home_score' => 0,
            'away_score' => 0,
            'home_points' => 0,
            'away_points' => 0,
            'wo_side' => null,
            'wo_reason' => null,
        ];
    }

    private function playContext(MatchReport $report): array
    {
        $report->load([
            'fixtureMatch.homeTeam',
            'fixtureMatch.awayTeam',
            'fixtureMatch.category',
            'fixtureMatch.tournament',
            'fixtureMatch.matchdayDate.matchday',
            'fixtureMatch.matchdayDate.court',
            'players.player',
            'players.team',
        ]);

        return [
            'report' => $report,
            'match' => $report->fixtureMatch,
            'homePlayers' => $report->players->where('team_side', 'home')->values(),
            'awayPlayers' => $report->players->where('team_side', 'away')->values(),
            'homeOptions' => $this->availablePlayersFor($report, 'home'),
            'awayOptions' => $this->availablePlayersFor($report, 'away'),
            'summary' => [
                'home_goals' => $report->players->where('team_side', 'home')->sum('goals'),
                'away_goals' => $report->players->where('team_side', 'away')->sum('goals'),
                'home_yellow_cards' => $report->players->where('team_side', 'home')->sum('yellow_cards'),
                'away_yellow_cards' => $report->players->where('team_side', 'away')->sum('yellow_cards'),
            ],
            'refreshUrl' => route('match-reports.matches.play', $report->fixtureMatch),
        ];
    }

    private function availablePlayersFor(MatchReport $report, string $side)
    {
        $teamId = $side === 'home'
            ? $report->fixtureMatch->home_team_id
            : $report->fixtureMatch->away_team_id;
        $usedIds = $report->players
            ->where('team_side', $side)
            ->pluck('tournament_team_player_id');

        return TournamentTeamPlayer::query()
            ->with('player')
            ->where('company_id', $report->company_id)
            ->where('tournament_id', $report->fixtureMatch->tournament_id)
            ->where('team_id', $teamId)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNotIn('id', $usedIds)
            ->orderBy('id')
            ->get();
    }

    private function syncScoreFromPlayers(MatchReport $report): void
    {
        $report->load('players');
        $report->update([
            'home_score' => $report->players->where('team_side', 'home')->sum('goals'),
            'away_score' => $report->players->where('team_side', 'away')->sum('goals'),
        ]);
    }

    private function statsUpdatesFor(MatchReportPlayer $player, string $field, int $delta): array
    {
        $currentValue = (int) $player->{$field};
        $nextValue = max(0, $currentValue + $delta);

        if ($field === 'yellow_cards') {
            if ($delta > 0 && $currentValue >= 2) {
                throw ValidationException::withMessages([
                    'yellow_cards' => 'Un jugador no puede tener mas de 2 tarjetas amarillas.',
                ]);
            }

            $nextValue = min(2, $nextValue);

            return ['yellow_cards' => $nextValue];
        }

        return [$field => $nextValue];
    }

    private function ensureEliminationCanBeClosed(MatchReport $report): void
    {
        $match = $report->fixtureMatch;

        if (! $match || ! $this->isEliminationMatch($match)) {
            return;
        }

        $tieMatches = $this->tieMatches($match);

        if ($tieMatches->count() === 1 && (int) $report->home_score === (int) $report->away_score) {
            throw ValidationException::withMessages([
                'score' => 'Este partido es de eliminacion directa y no puede finalizar empatado. Debe existir un ganador.',
            ]);
        }

        if (! $this->tieIsReadyToResolve($tieMatches, $report)) {
            return;
        }

        $aggregate = $this->aggregateForTie($tieMatches, $report);

        if ($aggregate->count() < 2) {
            return;
        }

        $ordered = $aggregate->sortByDesc('goals')->values();

        if ((int) $ordered[0]['goals'] === (int) $ordered[1]['goals']) {
            throw ValidationException::withMessages([
                'score' => 'El marcador global de la llave esta empatado. Debe registrarse un ganador antes de finalizar.',
            ]);
        }
    }

    private function advanceEliminationWinner(MatchReport $report): void
    {
        $match = $report->fixtureMatch;

        if (! $match || ! $this->isEliminationMatch($match)) {
            return;
        }

        $tieMatches = $this->tieMatches($match);

        if (! $this->tieIsReadyToResolve($tieMatches, $report)) {
            return;
        }

        $aggregate = $this->aggregateForTie($tieMatches, $report)->sortByDesc('goals')->values();

        if ($aggregate->count() < 2 || (int) $aggregate[0]['goals'] === (int) $aggregate[1]['goals']) {
            return;
        }

        $this->assignSeed($match, 'Ganador '.$match->stage.' '.$match->tie_number, $aggregate[0]);
        $this->assignSeed($match, 'Perdedor '.$match->stage.' '.$match->tie_number, $aggregate[1]);
    }

    private function isEliminationMatch(FixtureMatch $match): bool
    {
        return in_array($match->phase, ['knockout', 'league_playoff'], true);
    }

    private function tieMatches(FixtureMatch $match): Collection
    {
        if (! $match->tie_number) {
            return collect([$match->loadMissing('report')]);
        }

        return FixtureMatch::query()
            ->with('report')
            ->where('fixture_generation_id', $match->fixture_generation_id)
            ->where('phase', $match->phase)
            ->where('stage', $match->stage)
            ->where('tie_number', $match->tie_number)
            ->orderBy('leg_number')
            ->get();
    }

    private function tieIsReadyToResolve(Collection $tieMatches, MatchReport $currentReport): bool
    {
        return $tieMatches->every(function (FixtureMatch $match) use ($currentReport): bool {
            if ((int) $match->id === (int) $currentReport->fixture_match_id) {
                return true;
            }

            return in_array($match->report?->status, ['completed', 'walkover'], true);
        });
    }

    private function aggregateForTie(Collection $tieMatches, MatchReport $currentReport): Collection
    {
        $aggregate = collect();

        $tieMatches->each(function (FixtureMatch $match) use ($currentReport, $aggregate): void {
            $report = (int) $match->id === (int) $currentReport->fixture_match_id
                ? $currentReport
                : $match->report;

            if (! $report) {
                return;
            }

            $this->addAggregateGoals($aggregate, $match->home_registration_id, $match->home_team_id, (int) $report->home_score);
            $this->addAggregateGoals($aggregate, $match->away_registration_id, $match->away_team_id, (int) $report->away_score);
        });

        return $aggregate;
    }

    private function addAggregateGoals(Collection $aggregate, ?int $registrationId, ?int $teamId, int $goals): void
    {
        if (! $registrationId || ! $teamId) {
            return;
        }

        $key = (string) $registrationId;
        $row = $aggregate->get($key, [
            'registration_id' => $registrationId,
            'team_id' => $teamId,
            'goals' => 0,
        ]);
        $row['goals'] += $goals;
        $aggregate->put($key, $row);
    }

    private function assignSeed(FixtureMatch $sourceMatch, string $seed, array $team): void
    {
        FixtureMatch::query()
            ->where('fixture_generation_id', $sourceMatch->fixture_generation_id)
            ->where(function ($query) use ($seed): void {
                $query->where('home_seed', $seed)
                    ->orWhere('away_seed', $seed);
            })
            ->get()
            ->each(function (FixtureMatch $match) use ($seed, $team): void {
                $updates = [];

                if ($match->home_seed === $seed) {
                    $updates['home_registration_id'] = $team['registration_id'];
                    $updates['home_team_id'] = $team['team_id'];
                }

                if ($match->away_seed === $seed) {
                    $updates['away_registration_id'] = $team['registration_id'];
                    $updates['away_team_id'] = $team['team_id'];
                }

                if ($updates !== []) {
                    $match->update($updates);
                }
            });
    }

    private function clearAdvancedSeeds(MatchReport $report): void
    {
        $match = $report->fixtureMatch;

        if (! $match || ! $this->isEliminationMatch($match) || ! $match->tie_number) {
            return;
        }

        foreach (['Ganador '.$match->stage.' '.$match->tie_number, 'Perdedor '.$match->stage.' '.$match->tie_number] as $seed) {
            FixtureMatch::query()
                ->where('fixture_generation_id', $match->fixture_generation_id)
                ->where(function ($query) use ($seed): void {
                    $query->where('home_seed', $seed)
                        ->orWhere('away_seed', $seed);
                })
                ->get()
                ->each(function (FixtureMatch $dependentMatch) use ($seed): void {
                    $updates = [];

                    if ($dependentMatch->home_seed === $seed) {
                        $updates['home_registration_id'] = null;
                        $updates['home_team_id'] = null;
                    }

                    if ($dependentMatch->away_seed === $seed) {
                        $updates['away_registration_id'] = null;
                        $updates['away_team_id'] = null;
                    }

                    if ($updates !== []) {
                        $dependentMatch->update($updates);
                    }
                });
        }
    }

    private function pointsForScore(int $homeScore, int $awayScore): array
    {
        if ($homeScore === $awayScore) {
            return [
                'home_points' => 1,
                'away_points' => 1,
            ];
        }

        return [
            'home_points' => $homeScore > $awayScore ? 3 : 0,
            'away_points' => $awayScore > $homeScore ? 3 : 0,
        ];
    }
}
