<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRegistration\StoreTournamentRegistrationRequest;
use App\Http\Requests\TournamentRegistration\UpdateTournamentRegistrationRequest;
use App\Http\Requests\TournamentRegistration\UpdateTournamentRegistrationTeamNumberRequest;
use App\Models\Tournament;
use App\Models\TournamentRegistration;
use App\Services\TournamentRegistrationService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TournamentRegistrationController extends Controller
{
    public function __construct(
        private readonly TournamentRegistrationService $registrations
    ) {}

    public function index(): View
    {
        $registrationsByTournament = $this->registrations->groupedByTournament();
        $tournaments = $this->registrations->tournamentsForSelect(CompanyContext::id())
            ->filter(fn (Tournament $tournament): bool => $registrationsByTournament->has($tournament->id))
            ->values();
        $teams = $this->registrations->teamsForRegistrationList(CompanyContext::id());

        return view('tournament-registrations.index', [
            'registrationsByTournament' => $registrationsByTournament,
            'teams' => $teams,
            'tournaments' => $tournaments,
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData([
            'selectedTeam' => $this->registrations->teamForRegistration((int) $request->integer('team_id')),
        ]);

        if ($request->ajax()) {
            return view('tournament-registrations.partials.create-form', $data);
        }

        return view('tournament-registrations.create', $data);
    }

    public function store(StoreTournamentRegistrationRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $registration = $this->registrations->create($request->validated());
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
                'message' => 'Equipo inscrito correctamente.',
                'data' => ['id' => $registration->id],
            ], 201);
        }

        return redirect()->route('tournament-registrations.index')->with('success', 'Equipo inscrito correctamente.');
    }

    public function show(Request $request, TournamentRegistration $tournamentRegistration): View
    {
        $this->registrations->ensureVisible($tournamentRegistration);
        $tournamentRegistration->load(['company', 'category', 'tournament.season', 'tournament.division', 'tournament.categories', 'team']);

        if ($request->ajax()) {
            return view('tournament-registrations.partials.show', ['registration' => $tournamentRegistration]);
        }

        return view('tournament-registrations.show', ['registration' => $tournamentRegistration]);
    }

    public function edit(Request $request, TournamentRegistration $tournamentRegistration): View
    {
        $this->registrations->ensureVisible($tournamentRegistration);
        $tournamentRegistration->load(['category', 'tournament.categories', 'team']);

        $data = $this->formData(['registration' => $tournamentRegistration]);

        if ($request->ajax()) {
            return view('tournament-registrations.partials.edit-form', $data);
        }

        return view('tournament-registrations.edit', $data);
    }

    public function update(UpdateTournamentRegistrationRequest $request, TournamentRegistration $tournamentRegistration): JsonResponse|RedirectResponse
    {
        $registration = $this->registrations->update($tournamentRegistration, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Inscripcion actualizada correctamente.',
                'data' => ['id' => $registration->id],
            ]);
        }

        return redirect()->route('tournament-registrations.index')->with('success', 'Inscripcion actualizada correctamente.');
    }

    public function updateTeamNumber(UpdateTournamentRegistrationTeamNumberRequest $request, TournamentRegistration $tournamentRegistration): JsonResponse
    {
        try {
            $registration = $this->registrations->updateTeamNumber(
                $tournamentRegistration,
                (int) $request->validated('team_number')
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first(),
                'data' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Numero de equipo actualizado.',
            'data' => [
                'id' => $registration->id,
                'team_number' => $registration->team_number,
            ],
        ]);
    }

    public function destroy(TournamentRegistration $tournamentRegistration): RedirectResponse
    {
        $this->registrations->delete($tournamentRegistration);

        return redirect()->route('tournament-registrations.index')->with('success', 'Inscripcion eliminada correctamente.');
    }

    public function searchTeams(Request $request): JsonResponse
    {
        $teams = $this->registrations->searchTeams(
            $request->string('q')->toString(),
            $request->integer('tournament_id') ?: null
        );

        return response()->json([
            'data' => $teams->map(fn ($team): array => [
                'value' => (string) $team->id,
                'text' => $team->name,
            ])->values(),
        ]);
    }

    public function tournamentCategories(Request $request, Tournament $tournament): JsonResponse
    {
        $teamId = $request->integer('team_id') ?: null;
        $categories = $this->registrations->categoriesForTournament($tournament, $teamId);

        return response()->json([
            'data' => $categories->map(fn ($category): array => [
                'value' => (string) $category->id,
                'text' => $category->name,
            ])->values(),
            'already_registered' => $teamId ? $this->registrations->teamIsRegisteredInTournament($teamId, $tournament) : false,
        ]);
    }

    private function formData(array $data = []): array
    {
        $companyId = CompanyContext::id();

        return $data + [
            'tournaments' => $this->registrations->tournamentsForSelect($companyId),
            'divisions' => $this->registrations->divisionsForSelect($companyId),
            'teams' => collect(),
        ];
    }
}
