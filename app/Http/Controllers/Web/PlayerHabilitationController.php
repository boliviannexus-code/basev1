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
use Carbon\Carbon;
use Exception;
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
        return view('player-habilitations.index', [
            'tournaments' => $this->habilitations->tournamentsForSelect(),
        ]);
    }

    public function teams(Tournament $tournament): View
    {
        return view('player-habilitations.teams', $this->habilitations->tournamentContext($tournament));
    }

    public function show(Request $request, Tournament $tournament, Team $team): View
    {
        return view('player-habilitations.show', $this->habilitations->teamContext(
            $tournament,
            $team,
            $request->string('q')->toString()
        ));
    }

    public function affiliateForm(Request $request): View
    {
        return view('player-habilitations.partials.affiliate-form', [
            'ci' => $request->string('ci')->toString(),
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

    public function playerAge(Request $request): JsonResponse
    {
        try {
            $birthDate = $request->date('birth_date');
        } catch (Exception) {
            $birthDate = null;
        }

        if (! $birthDate || $birthDate->isToday() || $birthDate->isFuture()) {
            return response()->json([
                'age' => null,
                'label' => 'Fecha no valida',
            ]);
        }

        $age = (int) $birthDate->diffInYears(Carbon::today());

        return response()->json([
            'age' => $age,
            'label' => $age.' anos',
        ]);
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
            $habilitation = $this->habilitations->enable([
                'tournament_id' => $tournament->id,
                'team_player_id' => $teamPlayer->id,
                'notes' => $request->input('notes'),
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
                'message' => 'Jugador afiliado y habilitado al torneo correctamente.',
                'data' => ['id' => $teamPlayer->id, 'habilitation_id' => $habilitation->id],
            ]);
        }

        return redirect($this->redirectUrl($request))->with('success', 'Jugador afiliado y habilitado al torneo correctamente.');
    }

    public function enable(EnablePlayerRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $habilitation = $this->habilitations->enable($request->validated());
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
                'message' => 'Jugador habilitado correctamente.',
                'data' => ['habilitation_id' => $habilitation->id],
            ]);
        }

        return redirect($this->redirectUrl($request))->with('success', 'Jugador habilitado correctamente.');
    }

    public function destroy(DisablePlayerRequest $request, TournamentTeamPlayer $tournamentTeamPlayer): JsonResponse|RedirectResponse
    {
        $this->habilitations->disable($tournamentTeamPlayer);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Habilitacion retirada correctamente.',
            ]);
        }

        return redirect($this->redirectUrl($request))->with('success', 'Habilitacion retirada correctamente.');
    }

    private function redirectUrl(Request $request): string
    {
        $tournamentId = $request->integer('tournament_id') ?: null;
        $teamId = $request->integer('team_id') ?: null;

        if ($tournamentId && $teamId) {
            return route('player-habilitations.show', [
                'tournament' => $tournamentId,
                'team' => $teamId,
                'q' => $request->string('q')->toString() ?: null,
            ]);
        }

        if ($tournamentId) {
            return route('player-habilitations.teams', ['tournament' => $tournamentId]);
        }

        return route('player-habilitations.index');
    }
}
