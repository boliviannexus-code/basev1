<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\RedCard\StoreRedCardSanctionRequest;
use App\Models\FixtureMatch;
use App\Models\Matchday;
use App\Models\RedCardArticle;
use App\Models\RedCardSanction;
use App\Models\TournamentTeamPlayer;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RedCardController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()?->can('red-cards.view'), 403);

        $matchdays = Matchday::query()
            ->with(['season', 'dates.court'])
            ->withCount(['dates', 'fixtureMatches'])
            ->where('status', 'finalized')
            ->when(CompanyContext::id(), fn ($query, int $companyId) => $query->where('company_id', $companyId))
            ->orderByDesc('number')
            ->paginate(15);

        return view('red-cards.index', compact('matchdays'));
    }

    public function showMatchday(Matchday $matchday): View
    {
        $this->ensureVisibleFinalizedMatchday($matchday);

        $matchday->load([
            'season',
            'dates' => fn ($query) => $query->orderByRaw('COALESCE(sort_order, 2147483647)')->orderBy('date')->orderBy('id'),
            'dates.court',
            'dates.fixtureMatches' => fn ($query) => $query
                ->with(['homeTeam', 'awayTeam', 'category', 'tournament'])
                ->withCount('redCardSanctions')
                ->orderBy('scheduled_time')
                ->orderBy('match_number'),
        ]);

        return view('red-cards.matchday', compact('matchday'));
    }

    public function editMatch(FixtureMatch $fixtureMatch): View
    {
        $this->ensureVisibleFinalizedMatch($fixtureMatch);

        $fixtureMatch->load([
            'homeTeam',
            'awayTeam',
            'category',
            'tournament',
            'matchdayDate.matchday.season',
            'matchdayDate.court',
            'redCardSanctions.player',
            'redCardSanctions.team',
            'redCardSanctions.article',
        ]);

        return view('red-cards.match', [
            'match' => $fixtureMatch,
            'homeOptions' => $this->availablePlayersFor($fixtureMatch, 'home'),
            'awayOptions' => $this->availablePlayersFor($fixtureMatch, 'away'),
            'articles' => RedCardArticle::query()
                ->where('company_id', $fixtureMatch->company_id)
                ->orderBy('number')
                ->get(),
        ]);
    }

    public function store(StoreRedCardSanctionRequest $request, FixtureMatch $fixtureMatch): RedirectResponse
    {
        $this->ensureVisibleFinalizedMatch($fixtureMatch);
        abort_unless(auth()->user()?->can('red-cards.update'), 403);

        $side = $request->validated('team_side');
        $teamId = $side === 'home' ? $fixtureMatch->home_team_id : $fixtureMatch->away_team_id;

        $habilitation = TournamentTeamPlayer::query()
            ->with('player')
            ->whereKey($request->integer('tournament_team_player_id'))
            ->where('company_id', $fixtureMatch->company_id)
            ->where('tournament_id', $fixtureMatch->tournament_id)
            ->where('team_id', $teamId)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->firstOrFail();

        RedCardArticle::query()
            ->whereKey($request->integer('red_card_article_id'))
            ->where('company_id', $fixtureMatch->company_id)
            ->firstOrFail();

        RedCardSanction::query()->updateOrCreate([
            'fixture_match_id' => $fixtureMatch->id,
            'tournament_team_player_id' => $habilitation->id,
        ], [
            'company_id' => $fixtureMatch->company_id,
            'player_id' => $habilitation->player_id,
            'team_id' => $habilitation->team_id,
            'red_card_article_id' => $request->integer('red_card_article_id'),
            'jersey_number' => $request->integer('jersey_number'),
            'action_detail' => $request->validated('action_detail'),
            'suspended_matches' => $request->integer('suspended_matches'),
        ]);

        return redirect()->route('red-cards.matches.edit', $fixtureMatch)->with('success', 'Tarjeta roja registrada correctamente.');
    }

    public function destroy(RedCardSanction $redCardSanction): RedirectResponse
    {
        abort_unless(auth()->user()?->can('red-cards.update'), 403);
        abort_unless(CompanyContext::belongsToUser($redCardSanction->company_id, auth()->user()), 403);

        $match = $redCardSanction->fixtureMatch;
        $redCardSanction->delete();

        return redirect()->route('red-cards.matches.edit', $match)->with('success', 'Tarjeta roja eliminada correctamente.');
    }

    private function ensureVisibleFinalizedMatchday(Matchday $matchday): void
    {
        abort_unless(auth()->user()?->can('red-cards.view'), 403);
        abort_unless(CompanyContext::belongsToUser($matchday->company_id, auth()->user()), 403);
        abort_unless($matchday->status === 'finalized', 404);
    }

    private function ensureVisibleFinalizedMatch(FixtureMatch $match): void
    {
        abort_unless(auth()->user()?->can('red-cards.view'), 403);
        abort_unless(CompanyContext::belongsToUser($match->company_id, auth()->user()), 403);

        $match->loadMissing('matchdayDate.matchday');
        abort_unless($match->matchdayDate?->matchday?->status === 'finalized', 404);
    }

    private function availablePlayersFor(FixtureMatch $match, string $side)
    {
        $teamId = $side === 'home' ? $match->home_team_id : $match->away_team_id;
        $usedIds = $match->redCardSanctions->pluck('tournament_team_player_id');

        return TournamentTeamPlayer::query()
            ->with('player')
            ->where('company_id', $match->company_id)
            ->where('tournament_id', $match->tournament_id)
            ->where('team_id', $teamId)
            ->where('status', TournamentTeamPlayer::STATUS_ENABLED)
            ->whereNotIn('id', $usedIds)
            ->orderBy('id')
            ->get();
    }
}
