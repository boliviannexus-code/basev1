<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\MatchReportPlayer;
use App\Models\Player;
use App\Models\PlayerPunishment;
use App\Models\PlayerTransferRequest;
use App\Models\RedCardSanction;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamPlayer;
use App\Services\StandingsService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PublicLeaguePageController extends Controller
{
    public function __invoke(): View
    {
        $company = CompanyContext::tenantCompany();
        abort_unless($company instanceof Company && $company->public_page_is_enabled, 404);

        $latestMatchday = $this->latestMatchday($company);
        $latestMatches = $latestMatchday ? $this->publicFixtureMatches($company, $latestMatchday) : collect();

        return view('public.league', array_merge(
            $this->publicViewData($company),
            compact('latestMatchday', 'latestMatches')
        ));
    }

    public function standings(Request $request, StandingsService $standingsService): View
    {
        $company = $this->publicCompany();
        $tournaments = $this->publicTournaments($company);
        $selectedTournament = $request->filled('tournament_id')
            ? $tournaments->firstWhere('id', $request->integer('tournament_id'))
            : $tournaments->first();
        $groups = $selectedTournament ? $this->groupsFor($selectedTournament) : collect();
        $selectedGroup = $request->filled('group') ? $groups->firstWhere('value', $request->string('group')->toString()) : $groups->first();
        $standings = $selectedTournament && $selectedGroup
            ? $standingsService->table($selectedTournament, (int) $selectedGroup['category_id'], $selectedGroup['series'])
            : collect();

        return view('public.standings', array_merge(
            $this->publicViewData($company),
            compact('tournaments', 'selectedTournament', 'groups', 'selectedGroup', 'standings')
        ));
    }

    public function matches(Request $request): View
    {
        $company = $this->publicCompany();
        $filterMode = $request->input('filter_mode') === 'team' ? 'team' : 'matchday';
        $matchdays = Matchday::query()
            ->where('company_id', $company->id)
            ->whereHas('dates.fixtureMatches')
            ->orderByDesc('scheduled_date')
            ->orderByDesc('number')
            ->get();
        $selectedMatchday = $request->filled('matchday_id')
            ? $matchdays->firstWhere('id', $request->integer('matchday_id'))
            : null;
        $teams = Team::query()
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $selectedTeam = $filterMode === 'team' && $request->filled('team_id')
            ? $teams->firstWhere('id', $request->integer('team_id'))
            : null;
        $selectedMatchday = $filterMode === 'matchday' ? $selectedMatchday : null;
        $matches = $this->publicFixtureMatches($company, $selectedMatchday, $selectedTeam);

        return view('public.matches', array_merge(
            $this->publicViewData($company),
            compact('filterMode', 'matchdays', 'selectedMatchday', 'teams', 'selectedTeam', 'matches')
        ));
    }

    private function publicFixtureMatches(Company $company, ?Matchday $selectedMatchday = null, ?Team $selectedTeam = null): Collection
    {
        return FixtureMatch::query()
            ->select('fixture_matches.*')
            ->join('matchday_dates', 'matchday_dates.id', '=', 'fixture_matches.matchday_date_id')
            ->join('matchdays', 'matchdays.id', '=', 'matchday_dates.matchday_id')
            ->with(['category', 'homeTeam', 'awayTeam', 'report', 'matchdayDate.court', 'matchdayDate.matchday', 'matchdayDate.fiscals.team'])
            ->where('fixture_matches.company_id', $company->id)
            ->when($selectedMatchday, fn ($query, Matchday $matchday) => $query->where('matchday_dates.matchday_id', $matchday->id))
            ->when($selectedTeam, fn ($query, Team $team) => $query->where(fn ($teamQuery) => $teamQuery->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id)))
            ->whereNotNull('fixture_matches.matchday_date_id')
            ->orderByDesc('matchdays.number')
            ->orderByRaw('COALESCE(matchday_dates.sort_order, 2147483647)')
            ->orderBy('matchday_dates.date')
            ->orderBy('matchday_dates.id')
            ->orderBy('fixture_matches.scheduled_time')
            ->orderBy('fixture_matches.match_number')
            ->limit(80)
            ->get();
    }

    private function latestMatchday(Company $company): ?Matchday
    {
        return Matchday::query()
            ->where('company_id', $company->id)
            ->whereHas('dates.fixtureMatches')
            ->orderByDesc('number')
            ->orderByDesc('scheduled_date')
            ->first();
    }

    public function kardex(Request $request): View
    {
        $company = $this->publicCompany();
        $query = trim((string) $request->input('q'));
        $players = collect();
        $player = null;
        $kardex = null;

        if ($query !== '') {
            $players = Player::query()
                ->where('company_id', $company->id)
                ->where(function ($playerQuery) use ($query): void {
                    $like = '%'.str($query)->lower()->toString().'%';
                    $playerQuery
                        ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(maternal_name) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(internal_code) LIKE ?', [$like])
                        ->orWhere('ci_normalized', 'like', '%'.Player::normalizeCi($query).'%');
                })
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->limit(10)
                ->get();
            $player = $request->filled('player_id') ? $players->firstWhere('id', $request->integer('player_id')) : $players->first();

            if ($player) {
                $kardex = $this->publicPlayerKardexContext($player, $company);
            }
        }

        return view('public.kardex', array_merge(
            $this->publicViewData($company),
            compact('query', 'players', 'player', 'kardex')
        ));
    }

    public function image(Request $request, string $tenant, string $field): Response
    {
        $company = $this->publicCompany();
        $path = match ($field) {
            'banner' => $company->public_banner_path,
            'image-one' => $company->public_image_one_path,
            'image-two' => $company->public_image_two_path,
            default => null,
        };

        abort_unless($path && Storage::disk('public')->exists($path), 404);

        $disk = Storage::disk('public');

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function publicViewData(Company $company): array
    {
        $baseDomain = config('tenancy.base_domain');
        $whatsappNumber = preg_replace('/\D+/', '', (string) ($company->public_whatsapp ?: $company->phone));

        return [
            'company' => $company,
            'loginUrl' => request()->getScheme().'://'.$baseDomain.'/login',
            'socialLinks' => collect([
                'Facebook' => $company->public_facebook_url,
                'Instagram' => $company->public_instagram_url,
                'TikTok' => $company->public_tiktok_url,
                'YouTube' => $company->public_youtube_url,
            ])
                ->filter()
                ->map(fn (string $url): string => str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : 'https://'.$url),
            'whatsappUrl' => $whatsappNumber ? 'https://wa.me/'.$whatsappNumber : null,
            'publicImages' => [
                'banner' => $company->public_banner_path ? route('public.image', [request()->route('tenant'), 'banner']) : null,
                'image_one' => $company->public_image_one_path ? route('public.image', [request()->route('tenant'), 'image-one']) : null,
                'image_two' => $company->public_image_two_path ? route('public.image', [request()->route('tenant'), 'image-two']) : null,
            ],
        ];
    }

    private function publicPlayerKardexContext(Player $player, Company $company): array
    {
        $teamHistory = TeamPlayer::query()
            ->with(['team', 'division'])
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('joined_at')
            ->get();
        $habilitations = TournamentTeamPlayer::query()
            ->with(['team', 'tournament', 'tournamentRegistration.category', 'enabledBy'])
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('enabled_at')
            ->get();
        $transfers = PlayerTransferRequest::query()
            ->with(['fromTeam', 'toTeam', 'division', 'requester', 'reviewer'])
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('created_at')
            ->get();
        $redCards = RedCardSanction::query()
            ->with(['fixtureMatch.tournament', 'fixtureMatch.category', 'fixtureMatch.matchdayDate', 'team', 'article'])
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('created_at')
            ->get();
        $punishments = PlayerPunishment::query()
            ->with('article')
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('created_at')
            ->get();
        $matchStats = MatchReportPlayer::query()
            ->with(['team', 'matchReport.fixtureMatch.tournament', 'matchReport.fixtureMatch.category', 'matchReport.fixtureMatch.matchdayDate', 'matchReport.fixtureMatch.homeTeam', 'matchReport.fixtureMatch.awayTeam'])
            ->where('company_id', $company->id)
            ->where('player_id', $player->id)
            ->orderByDesc('created_at')
            ->get();

        return [
            'teamHistory' => $teamHistory,
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
            }) ?: $this->emptyTournamentSummary('Sin torneo');
            $key = (string) (array_search($current, $rows->all(), true) ?: 0);
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

    private function publicCompany(): Company
    {
        $company = CompanyContext::tenantCompany();
        abort_unless($company instanceof Company && $company->public_page_is_enabled, 404);

        return $company;
    }

    private function publicTournaments(Company $company): Collection
    {
        return Tournament::query()
            ->with('season')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get();
    }

    private function groupsFor(Tournament $tournament): Collection
    {
        return TournamentRegistration::query()
            ->with('category')
            ->where('company_id', $tournament->company_id)
            ->where('tournament_id', $tournament->id)
            ->where('status', 'registered')
            ->get()
            ->groupBy(fn (TournamentRegistration $registration): string => $registration->category_id.'|'.$registration->series)
            ->map(function (Collection $registrations, string $value): array {
                $registration = $registrations->first();

                return [
                    'value' => $value,
                    'category_id' => $registration->category_id,
                    'series' => $registration->series,
                    'label' => ($registration->category?->name ?? '-').' · '.$registration->seriesLabel(),
                ];
            })
            ->values();
    }
}
