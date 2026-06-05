<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\PlayerHabilitation\AffiliatePlayerRequest;
use App\Http\Requests\PlayerHabilitation\DisablePlayerRequest;
use App\Http\Requests\PlayerHabilitation\EnablePlayerRequest;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\TournamentTeamPlayer;
use App\Services\PlayerHabilitationService;
use App\Services\PlayerService;
use App\Services\TeamPlayerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlayerHabilitationController extends Controller
{
    public function __construct(
        private readonly PlayerHabilitationService $habilitations,
        private readonly PlayerService $players,
        private readonly TeamPlayerService $teamPlayers
    ) {}

    public function index(Request $request): View
    {
        return view('player-habilitations.index', $this->habilitations->context(
            $request->integer('tournament_id') ?: null,
            $request->integer('team_id') ?: null,
            $request->string('q')->toString()
        ));
    }

    public function affiliateForm(Request $request): View
    {
        return view('player-habilitations.partials.affiliate-form', [
            'teamId' => $request->integer('team_id') ?: null,
            'tournamentId' => $request->integer('tournament_id') ?: null,
        ]);
    }

    public function playerLookup(Request $request): JsonResponse
    {
        return response()->json($this->habilitations->playerLookup(
            $request->string('ci')->toString(),
            $request->integer('tournament_id') ?: null,
            $request->integer('team_id') ?: null
        ));
    }

    public function affiliate(AffiliatePlayerRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $tournament = Tournament::query()->whereKey($request->integer('tournament_id'))->firstOrFail();
            $team = Team::query()->whereKey($request->integer('team_id'))->firstOrFail();

            if (! $this->habilitations->registrationFor($tournament, $team)) {
                throw ValidationException::withMessages([
                    'team_id' => 'El equipo debe estar inscrito en el torneo antes de afiliar jugadores.',
                ]);
            }

            $player = $this->players->createOrReuse($request->validated() + ['is_active' => true]);
            $teamPlayer = $this->teamPlayers->affiliate($request->validated() + [
                'division_id' => $tournament->division_id,
                'player_id' => $player->id,
            ]);
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jugador afiliado al equipo correctamente.',
                'data' => ['id' => $teamPlayer->id],
            ]);
        }

        return redirect($this->redirectUrl($request))->with('success', 'Jugador afiliado al equipo correctamente.');
    }

    public function enable(EnablePlayerRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $habilitation = $this->habilitations->enable($request->validated());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect($this->redirectUrl($request))->with('success', 'Jugador habilitado correctamente.');
    }

    public function destroy(DisablePlayerRequest $request, TournamentTeamPlayer $tournamentTeamPlayer): RedirectResponse
    {
        $this->habilitations->disable($tournamentTeamPlayer);

        return redirect($this->redirectUrl($request))->with('success', 'Habilitacion retirada correctamente.');
    }

    private function redirectUrl(Request $request): string
    {
        return route('player-habilitations.index', [
            'tournament_id' => $request->integer('tournament_id') ?: null,
            'team_id' => $request->integer('team_id') ?: null,
            'q' => $request->string('q')->toString() ?: null,
        ]);
    }
}
