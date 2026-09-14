<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Models\PlayerPunishment;
use App\Models\PlayerTransferRequest;
use App\Models\MatchReportPlayer;
use App\Models\Matchday;
use App\Models\RedCardSanction;
use App\Models\Season;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Services\SportsReportPdfService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class SportsReportController extends Controller
{
    public function registeredTeams(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->filters($request);
        $this->validateRegisteredTeamsCategory($request);
        $rows = $this->registeredTeamsRows($filters);

        return view('sports-reports.registered-teams', $this->baseContext() + compact('filters', 'rows'));
    }

    public function enabledPlayers(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->filters($request);
        $this->validateRequiredCategory($request, 'Selecciona una categoria para ver el reporte de jugadores habilitados.');
        $rows = $this->enabledPlayersRows($filters);

        return view('sports-reports.enabled-players', $this->baseContext() + compact('filters', 'rows'));
    }

    public function transfers(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->filters($request);
        $rows = $this->transfersRows($filters);

        return view('sports-reports.transfers', $this->baseContext() + compact('filters', 'rows'));
    }

    public function registeredTeamsPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->filters($request);
        abort_unless($filters['category'], 422, 'Selecciona una categoria para imprimir el reporte.');
        $categoryName = $filters['tournament']?->categories->firstWhere('id', $filters['category'])?->name ?? 'Categoria';
        $rows = $this->registeredTeamsRows($filters);

        return $pdf->table('Equipos inscritos - '.$categoryName, $this->reportCompany($request), $this->pdfFilters($request), ['Nro.', 'Equipo', 'Torneo', 'Serie', 'Estado', 'Fecha/Hora', 'Usuario'], $rows->map(fn ($row) => [
            $row->team_number ?? '-',
            $row->team?->name ?? '-',
            $row->tournament?->name ?? '-',
            $row->seriesLabel(),
            'Inscrito',
            $row->created_at?->format('d/m/Y H:i') ?? '-',
            $row->creator?->name ?? '-',
        ]), 'reporte-equipos-inscritos-'.$categoryName, ['8%', '30%', '20%', '10%', '11%', '13%', '8%']);
    }

    public function enabledPlayersPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->filters($request);
        abort_unless($filters['category'], 422, 'Selecciona una categoria para imprimir el reporte.');
        $categoryName = $filters['tournament']?->categories->firstWhere('id', $filters['category'])?->name ?? 'Categoria';
        $rows = $this->enabledPlayersRows($filters);

        return $pdf->table('Jugadores habilitados - '.$categoryName, $this->reportCompany($request), $this->pdfFilters($request), ['Serie', 'Equipo', 'Cod. jugador', 'Jugador', 'CI', 'Fecha/Hora', 'Usuario'], $rows->map(fn ($row) => [
            $row->tournamentRegistration?->seriesLabel() ?? '-',
            $row->team?->name ?? '-',
            $row->player?->internal_code ?? '-',
            $row->player?->full_name ?? '-',
            $row->player?->ci ?? '-',
            $row->enabled_at?->format('d/m/Y H:i') ?? '-',
            $row->enabledBy?->name ?? '-',
        ]), 'reporte-jugadores-habilitados-'.$categoryName, ['9%', '22%', '13%', '24%', '10%', '14%', '8%']);
    }

    public function transfersPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $rows = $this->transfersRows($this->filters($request));

        return $pdf->table('Reporte de pases', $this->reportCompany($request), $this->pdfFilters($request), ['Codigo', 'Cod. jugador', 'Jugador', 'Origen', 'Destino', 'Estado', 'Fecha/Hora', 'Usuario'], $rows->map(fn ($row) => [
            $row->code,
            $row->player?->internal_code ?? '-',
            $row->player?->full_name ?? '-',
            $row->fromTeam?->name ?? '-',
            $row->toTeam?->name ?? '-',
            player_transfer_status_label($row->status),
            $row->created_at?->format('d/m/Y H:i') ?? '-',
            $row->requester?->name ?? '-',
        ]), 'reporte-pases');
    }

    public function playerKardexPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        abort_unless($request->filled('player_id'), 404);
        $this->authorizeReports();

        $player = Player::query()
            ->with('company')
            ->forCompany(CompanyContext::id())
            ->findOrFail($request->integer('player_id'));
        $context = $this->playerKardexContext($player);

        return $pdf->kardex($player, $context, 'kardex-'.$player->full_name);
    }

    public function playerKardex(Request $request): View
    {
        $this->authorizeReports();

        $player = null;
        $context = [
            'teamHistory' => collect(),
            'habilitations' => collect(),
            'transfers' => collect(),
            'redCards' => collect(),
            'punishments' => collect(),
        ];

        if ($request->filled('player_id')) {
            $player = Player::query()
                ->with('company')
                ->forCompany(CompanyContext::id())
                ->whereKey($request->integer('player_id'))
                ->firstOrFail();

            $context = $this->playerKardexContext($player);
        }

        return view('sports-reports.player-kardex', $this->baseContext() + [
            'playerOptions' => $this->playerOptions(),
            'player' => $player,
            'context' => $context,
        ]);
    }

    public function finalizedMatchdays(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->matchdayReportFilters($request);
        $rows = $this->finalizedMatchdaysRows($filters);

        return view('sports-reports.finalized-matchdays', $this->baseContext() + compact('filters', 'rows'));
    }

    public function finalizedMatchdaysPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->matchdayReportFilters($request);
        $rows = $this->finalizedMatchdaysRows($filters);

        return $pdf->table('Reporte de jornadas finalizadas', $this->reportCompany($request), $this->matchdayPdfFilters($filters), ['Nro.', 'Jornada', 'Gestion', 'Fecha programada', 'Dias', 'Partidos', 'Estado'], $rows->map(fn (Matchday $row) => [
            $row->number ?? '-',
            $row->name,
            $row->season?->name ?? '-',
            $row->scheduled_date?->format('d/m/Y') ?? '-',
            $row->dates_count,
            $row->fixture_matches_count,
            'Finalizada',
        ]), 'reporte-jornadas-finalizadas', ['8%', '28%', '18%', '16%', '8%', '10%', '12%']);
    }

    public function matchdayResultsPdf(Matchday $matchday, SportsReportPdfService $pdf): Response
    {
        $this->authorizeReports();
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_unless($matchday->status === 'finalized', 404);

        $matchday->loadMissing([
            'company',
            'season',
            'dates' => fn ($query) => $query
                ->orderByRaw('COALESCE(sort_order, 2147483647)')
                ->orderBy('date')
                ->orderBy('id'),
            'dates.court',
            'dates.fixtureMatches' => fn ($query) => $query
                ->with(['category', 'homeTeam', 'awayTeam', 'report'])
                ->orderBy('scheduled_time')
                ->orderBy('match_number'),
        ]);

        return $pdf->matchdayResults($matchday, $matchday->dates, 'reporte-resultados-'.$matchday->name);
    }

    public function yellowCards(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->matchdayReportFilters($request);
        $rows = $this->yellowCardRows($filters);

        return view('sports-reports.yellow-cards', $this->baseContext() + compact('filters', 'rows'));
    }

    public function yellowCardsPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->matchdayReportFilters($request);
        abort_unless($filters['matchday'], 422, 'Selecciona una jornada para imprimir el reporte.');
        $rows = $this->yellowCardRows($filters);

        return $pdf->table('Tarjetas amarillas - '.$filters['matchday']->name, $this->reportCompany($request), $this->matchdayPdfFilters($filters), ['Fecha', 'Torneo', 'Categoria', 'Equipo', 'Jugador', 'TA', 'Acum. torneo'], $rows->map(fn (array $row) => [
            $row['date'],
            $row['tournament'],
            $row['category'],
            $row['team'],
            $row['player'],
            $row['yellow_cards'],
            $row['tournament_yellow_cards'],
        ]), 'reporte-tarjetas-amarillas-'.$filters['matchday']->name, ['11%', '19%', '15%', '17%', '25%', '6%', '7%']);
    }

    public function redCards(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->matchdayReportFilters($request);
        $rows = $this->redCardRows($filters);

        return view('sports-reports.red-cards', $this->baseContext() + compact('filters', 'rows'));
    }

    public function redCardsPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->matchdayReportFilters($request);
        abort_unless($filters['matchday'], 422, 'Selecciona una jornada para imprimir el reporte.');
        $rows = $this->redCardRows($filters);

        return $pdf->table('Tarjetas rojas - '.$filters['matchday']->name, $this->reportCompany($request), $this->matchdayPdfFilters($filters), ['Fecha', 'Torneo', 'Categoria', 'Equipo', 'Jugador', 'TR', 'Acum. torneo', 'Part.'], $rows->map(fn (array $row) => [
            $row['date'],
            $row['tournament'],
            $row['category'],
            $row['team'],
            $row['player'],
            $row['red_cards'],
            $row['tournament_red_cards'],
            $row['suspended_matches'],
        ]), 'reporte-tarjetas-rojas-'.$filters['matchday']->name, ['10%', '17%', '13%', '15%', '24%', '5%', '8%', '8%']);
    }

    public function cardSummary(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->matchdayReportFilters($request);
        $rows = $this->cardSummaryRows($filters);

        return view('sports-reports.card-summary', $this->baseContext() + compact('filters', 'rows'));
    }

    public function cardSummaryPdf(Request $request, SportsReportPdfService $pdf): Response
    {
        $filters = $this->matchdayReportFilters($request);
        $rows = $this->cardSummaryRows($filters);

        return $pdf->table('Acumulado de tarjetas por categoria', $this->reportCompany($request), $this->matchdayPdfFilters($filters), ['Categoria', 'Amarillas', 'Rojas', 'Total'], $rows->map(fn (array $row) => [
            $row['category'],
            $row['yellow_cards'],
            $row['red_cards'],
            $row['total'],
        ]), 'reporte-acumulado-tarjetas', ['46%', '18%', '18%', '18%']);
    }

    private function authorizeReports(): void
    {
        abort_unless(auth()->user()?->can('sports-reports.view'), 403);
    }

    private function playerKardexContext(Player $player): array
    {
        $companyId = CompanyContext::id();

        $teamHistory = TeamPlayer::query()
            ->with(['team', 'division'])
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('joined_at')
            ->get();
        $habilitations = TournamentTeamPlayer::query()
            ->with(['team', 'tournament', 'tournamentRegistration.category', 'enabledBy'])
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('enabled_at')
            ->get();
        $transfers = PlayerTransferRequest::query()
            ->with(['fromTeam', 'toTeam', 'division', 'requester', 'reviewer'])
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get();
        $redCards = RedCardSanction::query()
            ->with(['fixtureMatch.tournament', 'fixtureMatch.category', 'fixtureMatch.matchdayDate', 'team', 'article'])
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get();
        $punishments = PlayerPunishment::query()
            ->with('article')
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get();
        $matchStats = MatchReportPlayer::query()
            ->with(['team', 'matchReport.fixtureMatch.tournament', 'matchReport.fixtureMatch.category', 'matchReport.fixtureMatch.matchdayDate', 'matchReport.fixtureMatch.homeTeam', 'matchReport.fixtureMatch.awayTeam'])
            ->where('player_id', $player->id)
            ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('created_at')
            ->get();

        return [
            'company' => CompanyContext::activeCompany(auth()->user()) ?? $teamHistory->first()?->team?->company,
            'teamHistory' => $teamHistory,
            'habilitations' => $habilitations,
            'transfers' => $transfers,
            'redCards' => $redCards,
            'punishments' => $punishments,
            'matchStats' => $matchStats,
            'summary' => [
                'teams' => $teamHistory->pluck('team_id')->filter()->unique()->count(),
                'habilitations' => $habilitations->count(),
                'matches' => $matchStats->count(),
                'goals' => $matchStats->sum('goals'),
                'yellow_cards' => $matchStats->sum('yellow_cards'),
                'red_cards' => $redCards->count(),
                'transfers' => $transfers->count(),
                'punishments' => $punishments->count(),
            ],
            'tournamentSummary' => $this->playerKardexTournamentSummary($matchStats, $habilitations, $transfers, $redCards),
            'details' => $this->playerKardexDetails($matchStats, $habilitations, $transfers, $redCards, $punishments),
        ];
    }

    private function playerKardexTournamentSummary(Collection $matchStats, Collection $habilitations, Collection $transfers, Collection $redCards): Collection
    {
        $rows = collect();

        foreach ($matchStats as $row) {
            $match = $row->matchReport?->fixtureMatch;
            $key = (string) ($match?->tournament_id ?? 0);
            $current = $rows->get($key, $this->emptyTournamentSummary($match?->tournament?->name ?? 'Sin torneo'));
            $current['matches']++;
            $current['goals'] += (int) $row->goals;
            $current['yellow_cards'] += (int) $row->yellow_cards;
            $current['teams'][$row->team?->name ?? '-'] = true;
            $rows->put($key, $current);
        }

        foreach ($redCards as $row) {
            $key = (string) ($row->fixtureMatch?->tournament_id ?? 0);
            $current = $rows->get($key, $this->emptyTournamentSummary($row->fixtureMatch?->tournament?->name ?? 'Sin torneo'));
            $current['red_cards']++;
            $current['suspended_matches'] += (int) $row->suspended_matches;
            $current['teams'][$row->team?->name ?? '-'] = true;
            $rows->put($key, $current);
        }

        foreach ($habilitations as $row) {
            $key = (string) ($row->tournament_id ?? 0);
            $current = $rows->get($key, $this->emptyTournamentSummary($row->tournament?->name ?? 'Sin torneo'));
            $current['habilitations']++;
            $current['teams'][$row->team?->name ?? '-'] = true;
            $rows->put($key, $current);
        }

        foreach ($transfers as $row) {
            $current = $rows->first(function (array $summary) use ($row): bool {
                return isset($summary['teams'][$row->fromTeam?->name ?? '']) || isset($summary['teams'][$row->toTeam?->name ?? '']);
            });

            if (! $current) {
                $current = $this->emptyTournamentSummary('Sin torneo');
            }

            $key = array_search($current, $rows->all(), true);
            $key = $key === false ? '0' : (string) $key;
            $current['transfers']++;
            $current['teams'][$row->fromTeam?->name ?? '-'] = true;
            $current['teams'][$row->toTeam?->name ?? '-'] = true;
            $rows->put($key, $current);
        }

        return $rows
            ->map(function (array $row): array {
                $row['teams_label'] = collect(array_keys($row['teams']))->filter(fn (string $team): bool => $team !== '-')->sort()->join(', ') ?: '-';
                unset($row['teams']);

                return $row;
            })
            ->sortBy('tournament')
            ->values();
    }

    private function emptyTournamentSummary(string $tournament): array
    {
        return [
            'tournament' => $tournament,
            'teams' => [],
            'habilitations' => 0,
            'matches' => 0,
            'goals' => 0,
            'yellow_cards' => 0,
            'red_cards' => 0,
            'suspended_matches' => 0,
            'transfers' => 0,
        ];
    }

    private function playerKardexDetails(Collection $matchStats, Collection $habilitations, Collection $transfers, Collection $redCards, Collection $punishments): Collection
    {
        return collect()
            ->merge($matchStats->map(function (MatchReportPlayer $row): array {
                $match = $row->matchReport?->fixtureMatch;

                return [
                    'date' => $match?->matchdayDate?->date ?? $row->created_at,
                    'type' => 'Partido jugado',
                    'team' => $row->team?->name ?? '-',
                    'competition' => trim(($match?->tournament?->name ?? '-').' · '.($match?->category?->name ?? '-')),
                    'detail' => ($match?->homeTeam?->name ?? '-').' vs '.($match?->awayTeam?->name ?? '-'),
                    'stats' => 'Goles: '.$row->goals.' · Amarillas: '.$row->yellow_cards,
                ];
            }))
            ->merge($habilitations->map(fn (TournamentTeamPlayer $row): array => [
                'date' => $row->enabled_at,
                'type' => 'Habilitacion',
                'team' => $row->team?->name ?? '-',
                'competition' => $row->tournament?->name ?? '-',
                'detail' => $row->tournamentRegistration?->category?->name ?? '-',
                'stats' => 'Usuario: '.($row->enabledBy?->name ?? '-'),
            ]))
            ->merge($transfers->map(fn (PlayerTransferRequest $row): array => [
                'date' => $row->created_at,
                'type' => 'Pase',
                'team' => $row->toTeam?->name ?? '-',
                'competition' => $row->division?->name ?? '-',
                'detail' => ($row->fromTeam?->name ?? '-').' -> '.($row->toTeam?->name ?? '-'),
                'stats' => player_transfer_status_label($row->status).' · '.($row->requester?->name ?? '-'),
            ]))
            ->merge($redCards->map(fn (RedCardSanction $row): array => [
                'date' => $row->created_at,
                'type' => 'Tarjeta roja',
                'team' => $row->team?->name ?? '-',
                'competition' => $row->fixtureMatch?->tournament?->name ?? '-',
                'detail' => 'Art. '.($row->article?->number ?? '-').' · '.$row->action_detail,
                'stats' => $row->suspended_matches.' partido(s)',
            ]))
            ->merge($punishments->map(fn (PlayerPunishment $row): array => [
                'date' => $row->starts_on,
                'type' => 'Castigo',
                'team' => '-',
                'competition' => 'Art. '.($row->article?->number ?? '-'),
                'detail' => $row->reason,
                'stats' => $row->status.' · '.($row->ends_on?->format('d/m/Y') ?? 'sin fin'),
            ]))
            ->sortByDesc(fn (array $row) => $row['date']?->timestamp ?? 0)
            ->values();
    }

    private function registeredTeamsRows(array $filters): Collection
    {
        if (! $filters['tournament'] || ! $filters['category']) {
            return collect();
        }

        return TournamentRegistration::query()
            ->with(['team', 'category', 'division', 'tournament', 'creator'])
            ->where('tournament_id', $filters['tournament']->id)
            ->when($filters['category'], fn ($query) => $query->where('category_id', $filters['category']))
            ->where('tournament_registrations.status', 'registered')
            ->join('teams', 'teams.id', '=', 'tournament_registrations.team_id')
            ->join('division_categories', 'division_categories.id', '=', 'tournament_registrations.category_id')
            ->orderBy('division_categories.name')
            ->orderBy('tournament_registrations.series')
            ->orderBy('tournament_registrations.team_number')
            ->orderBy('teams.name')
            ->select('tournament_registrations.*')
            ->get();
    }

    private function finalizedMatchdaysRows(array $filters): Collection
    {
        return Matchday::query()
            ->with('season')
            ->withCount(['dates', 'fixtureMatches'])
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->when($filters['season'], fn ($query, Season $season) => $query->where('season_id', $season->id))
            ->where('status', 'finalized')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('number')
            ->get();
    }

    private function yellowCardRows(array $filters): Collection
    {
        if (! $filters['matchday']) {
            return collect();
        }

        $rows = MatchReportPlayer::query()
            ->with([
                'player',
                'team',
                'matchReport.fixtureMatch.category',
                'matchReport.fixtureMatch.tournament',
                'matchReport.fixtureMatch.matchdayDate.matchday',
                'matchReport.fixtureMatch.homeTeam',
                'matchReport.fixtureMatch.awayTeam',
            ])
            ->where('yellow_cards', '>', 0)
            ->whereHas('matchReport.fixtureMatch.matchdayDate', fn ($query) => $query->where('matchday_id', $filters['matchday']->id))
            ->get();

        $accumulated = $this->yellowTournamentAccumulated($rows);

        return $rows
            ->map(function (MatchReportPlayer $row) use ($accumulated): array {
                $match = $row->matchReport?->fixtureMatch;
                $key = $this->cardAccumulatedKey($match?->tournament_id, $row->player_id);

                return [
                    'date' => $match?->matchdayDate?->date?->format('d/m/Y') ?? '-',
                    'matchday' => $match?->matchdayDate?->matchday?->name ?? '-',
                    'tournament' => $match?->tournament?->name ?? '-',
                    'category' => $match?->category?->name ?? '-',
                    'match' => ($match?->homeTeam?->name ?? '-').' vs '.($match?->awayTeam?->name ?? '-'),
                    'team' => $row->team?->name ?? '-',
                    'player' => $row->player?->full_name ?? '-',
                    'yellow_cards' => (int) $row->yellow_cards,
                    'tournament_yellow_cards' => $accumulated[$key] ?? (int) $row->yellow_cards,
                ];
            })
            ->sortBy([
                fn (array $row): string => $row['tournament'],
                fn (array $row): string => $row['category'],
                fn (array $row): string => $row['team'],
                fn (array $row): string => $row['player'],
            ])
            ->values();
    }

    private function redCardRows(array $filters): Collection
    {
        if (! $filters['matchday']) {
            return collect();
        }

        $rows = RedCardSanction::query()
            ->with([
                'player',
                'team',
                'article',
                'fixtureMatch.category',
                'fixtureMatch.tournament',
                'fixtureMatch.matchdayDate.matchday',
                'fixtureMatch.homeTeam',
                'fixtureMatch.awayTeam',
            ])
            ->whereHas('fixtureMatch.matchdayDate', fn ($query) => $query->where('matchday_id', $filters['matchday']->id))
            ->get();

        $accumulated = $this->redTournamentAccumulated($rows);

        return $rows
            ->map(function (RedCardSanction $row) use ($accumulated): array {
                $match = $row->fixtureMatch;
                $key = $this->cardAccumulatedKey($match?->tournament_id, $row->player_id);

                return [
                    'date' => $match?->matchdayDate?->date?->format('d/m/Y') ?? '-',
                    'matchday' => $match?->matchdayDate?->matchday?->name ?? '-',
                    'tournament' => $match?->tournament?->name ?? '-',
                    'category' => $match?->category?->name ?? '-',
                    'match' => ($match?->homeTeam?->name ?? '-').' vs '.($match?->awayTeam?->name ?? '-'),
                    'team' => $row->team?->name ?? '-',
                    'player' => $row->player?->full_name ?? '-',
                    'red_cards' => 1,
                    'tournament_red_cards' => $accumulated[$key] ?? 1,
                    'article' => $row->article?->number ?? '-',
                    'detail' => $row->action_detail,
                    'suspended_matches' => (int) $row->suspended_matches,
                ];
            })
            ->sortBy([
                fn (array $row): string => $row['tournament'],
                fn (array $row): string => $row['category'],
                fn (array $row): string => $row['team'],
                fn (array $row): string => $row['player'],
            ])
            ->values();
    }

    private function yellowTournamentAccumulated(Collection $rows): array
    {
        $tournamentIds = $rows
            ->map(fn (MatchReportPlayer $row) => $row->matchReport?->fixtureMatch?->tournament_id)
            ->filter()
            ->unique()
            ->values();
        $playerIds = $rows->pluck('player_id')->filter()->unique()->values();

        if ($tournamentIds->isEmpty() || $playerIds->isEmpty()) {
            return [];
        }

        return MatchReportPlayer::query()
            ->with('matchReport.fixtureMatch')
            ->whereIn('player_id', $playerIds)
            ->where('yellow_cards', '>', 0)
            ->whereHas('matchReport.fixtureMatch', fn ($query) => $query->whereIn('tournament_id', $tournamentIds))
            ->get()
            ->groupBy(fn (MatchReportPlayer $row): string => $this->cardAccumulatedKey($row->matchReport?->fixtureMatch?->tournament_id, $row->player_id))
            ->map(fn (Collection $group): int => (int) $group->sum('yellow_cards'))
            ->all();
    }

    private function redTournamentAccumulated(Collection $rows): array
    {
        $tournamentIds = $rows
            ->map(fn (RedCardSanction $row) => $row->fixtureMatch?->tournament_id)
            ->filter()
            ->unique()
            ->values();
        $playerIds = $rows->pluck('player_id')->filter()->unique()->values();

        if ($tournamentIds->isEmpty() || $playerIds->isEmpty()) {
            return [];
        }

        return RedCardSanction::query()
            ->with('fixtureMatch')
            ->whereIn('player_id', $playerIds)
            ->whereHas('fixtureMatch', fn ($query) => $query->whereIn('tournament_id', $tournamentIds))
            ->get()
            ->groupBy(fn (RedCardSanction $row): string => $this->cardAccumulatedKey($row->fixtureMatch?->tournament_id, $row->player_id))
            ->map(fn (Collection $group): int => $group->count())
            ->all();
    }

    private function cardAccumulatedKey(?int $tournamentId, ?int $playerId): string
    {
        return ($tournamentId ?? 0).'|'.($playerId ?? 0);
    }

    private function cardSummaryRows(array $filters): Collection
    {
        $yellowRows = MatchReportPlayer::query()
            ->with('matchReport.fixtureMatch.category')
            ->where('yellow_cards', '>', 0)
            ->when($filters['season'], function ($query, Season $season): void {
                $query->whereHas('matchReport.fixtureMatch.matchdayDate.matchday', fn ($matchdayQuery) => $matchdayQuery->where('season_id', $season->id));
            })
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->get();
        $redRows = RedCardSanction::query()
            ->with('fixtureMatch.category')
            ->when($filters['season'], function ($query, Season $season): void {
                $query->whereHas('fixtureMatch.matchdayDate.matchday', fn ($matchdayQuery) => $matchdayQuery->where('season_id', $season->id));
            })
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->get();

        $rows = collect();

        foreach ($yellowRows as $row) {
            $category = $row->matchReport?->fixtureMatch?->category?->name ?? 'Sin categoria';
            $current = $rows->get($category, ['category' => $category, 'yellow_cards' => 0, 'red_cards' => 0]);
            $current['yellow_cards'] += (int) $row->yellow_cards;
            $rows->put($category, $current);
        }

        foreach ($redRows as $row) {
            $category = $row->fixtureMatch?->category?->name ?? 'Sin categoria';
            $current = $rows->get($category, ['category' => $category, 'yellow_cards' => 0, 'red_cards' => 0]);
            $current['red_cards']++;
            $rows->put($category, $current);
        }

        return $rows
            ->map(function (array $row): array {
                $row['total'] = $row['yellow_cards'] + $row['red_cards'];

                return $row;
            })
            ->sortBy('category')
            ->values();
    }

    private function enabledPlayersRows(array $filters): Collection
    {
        if (! $filters['tournament'] || ! $filters['category']) {
            return collect();
        }

        return TournamentTeamPlayer::query()
            ->with(['player', 'team', 'tournamentRegistration.category', 'tournament', 'enabledBy'])
            ->where('tournament_team_players.tournament_id', $filters['tournament']->id)
            ->where('tournament_team_players.status', TournamentTeamPlayer::STATUS_ENABLED)
            ->when($filters['category'], fn ($query) => $query->where('tournament_registrations.category_id', $filters['category']))
            ->when($filters['team'], fn ($query) => $query->where('tournament_team_players.team_id', $filters['team']))
            ->join('tournament_registrations', 'tournament_registrations.id', '=', 'tournament_team_players.tournament_registration_id')
            ->join('division_categories', 'division_categories.id', '=', 'tournament_registrations.category_id')
            ->join('teams', 'teams.id', '=', 'tournament_team_players.team_id')
            ->join('players', 'players.id', '=', 'tournament_team_players.player_id')
            ->orderBy('division_categories.name')
            ->orderBy('tournament_registrations.series')
            ->orderBy('teams.name')
            ->orderBy('players.last_name')
            ->orderBy('players.first_name')
            ->select('tournament_team_players.*')
            ->get();
    }

    private function transfersRows(array $filters): Collection
    {
        if (! $filters['tournament']) {
            return collect();
        }

        $registrations = TournamentRegistration::query()
            ->with(['category', 'team'])
            ->where('tournament_id', $filters['tournament']->id)
            ->when($filters['category'], fn ($query) => $query->where('category_id', $filters['category']))
            ->when($filters['team'], fn ($query) => $query->where('team_id', $filters['team']))
            ->get();
        $teamIds = $registrations->pluck('team_id');

        return PlayerTransferRequest::query()
            ->with(['player', 'division', 'fromTeam', 'toTeam', 'requester', 'reviewer'])
            ->where('company_id', $filters['tournament']->company_id)
            ->where('division_id', $filters['tournament']->division_id)
            ->when($filters['date_from'], fn ($query, string $date) => $query->whereDate('created_at', '>=', $date))
            ->when($filters['date_to'], fn ($query, string $date) => $query->whereDate('created_at', '<=', $date))
            ->where(function ($query) use ($teamIds): void {
                $query->whereIn('from_team_id', $teamIds)
                    ->orWhereIn('to_team_id', $teamIds);
            })
            ->get()
            ->sortBy([
                fn (PlayerTransferRequest $transfer): string => $registrations->firstWhere('team_id', $transfer->to_team_id)?->category?->name ?? '',
                fn (PlayerTransferRequest $transfer): string => $registrations->firstWhere('team_id', $transfer->to_team_id)?->series ?? '',
                fn (PlayerTransferRequest $transfer): string => $transfer->toTeam?->name ?? '',
                fn (PlayerTransferRequest $transfer): string => $transfer->created_at?->format('YmdHis') ?? '',
            ])
            ->values();
    }

    private function reportCompany(Request $request)
    {
        return $this->filters($request)['tournament']?->company
            ?? $this->matchdayReportFilters($request)['matchday']?->company
            ?? CompanyContext::activeCompany(auth()->user());
    }

    private function matchdayReportFilters(Request $request): array
    {
        $season = null;
        $matchday = null;

        if ($request->filled('season_id')) {
            $season = Season::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->whereKey($request->integer('season_id'))
                ->firstOrFail();
        }

        if (! $season && ! $request->filled('matchday_id')) {
            $season = Season::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->first();
        }

        if ($request->filled('matchday_id')) {
            $matchday = Matchday::query()
                ->with(['company', 'season'])
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('status', 'finalized')
                ->whereKey($request->integer('matchday_id'))
                ->firstOrFail();
            $season ??= $matchday->season;
        }

        return [
            'season' => $season,
            'matchday' => $matchday,
        ];
    }

    private function matchdayPdfFilters(array $filters): array
    {
        return [
            'tournament' => $filters['season']?->name ?? 'Todas las gestiones',
            'category' => $filters['matchday']?->name ?? 'Todas',
            'series' => 'Todas',
        ];
    }

    private function pdfFilters(Request $request): array
    {
        $filters = $this->filters($request);
        $category = $filters['tournament']?->categories->firstWhere('id', $filters['category']);

        return [
            'tournament' => $filters['tournament']?->name ?? '-',
            'category' => $category?->name ?? 'Todas',
            'series' => 'Todas',
            'date_from' => $filters['date_from'] ? date('d/m/Y', strtotime($filters['date_from'])) : null,
            'date_to' => $filters['date_to'] ? date('d/m/Y', strtotime($filters['date_to'])) : null,
        ];
    }

    private function validateRegisteredTeamsCategory(Request $request): void
    {
        $this->validateRequiredCategory($request, 'Selecciona una categoria para ver el reporte de equipos inscritos.');
    }

    private function validateRequiredCategory(Request $request, string $message): void
    {
        if ($request->filled('tournament_id') && ! $request->filled('category_id')) {
            session()->flash('warning', $message);
        }
    }

    private function baseContext(): array
    {
        $tournaments = Tournament::query()
            ->with('categories')
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->where('status', 'active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return [
            'tournaments' => $tournaments,
            'seasons' => Season::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->orderByDesc('year')
                ->orderByDesc('id')
                ->get(),
            'finalizedMatchdays' => Matchday::query()
                ->with('season')
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('status', 'finalized')
                ->orderByDesc('scheduled_date')
                ->orderByDesc('number')
                ->get(),
            'teams' => Team::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }

    private function filters(Request $request): array
    {
        $tournament = null;

        if ($request->filled('tournament_id')) {
            $tournament = Tournament::query()
                ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->whereKey($request->integer('tournament_id'))
                ->firstOrFail();
        }

        return [
            'tournament' => $tournament,
            'category' => $request->integer('category_id') ?: null,
            'team' => $request->integer('team_id') ?: null,
            'date_from' => $this->dateInput($request, 'date_from'),
            'date_to' => $this->dateInput($request, 'date_to'),
        ];
    }

    private function dateInput(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }

    private function playerOptions()
    {
        return Player::query()
            ->forCompany(CompanyContext::id())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }
}
