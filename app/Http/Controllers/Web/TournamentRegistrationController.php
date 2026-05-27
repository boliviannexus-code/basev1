<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentRegistration\StoreTournamentRegistrationRequest;
use App\Http\Requests\TournamentRegistration\UpdateTournamentRegistrationRequest;
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
        $divisionId = request()->integer('division_id') ?: null;
        $divisions = $this->registrations->divisionsForSelect(CompanyContext::id());
        $activeDivisionId = $divisionId ?? $divisions->first()?->id;
        $tournaments = $this->registrations->tournamentsForSelect(CompanyContext::id())
            ->when($activeDivisionId, fn ($collection, $divisionId) => $collection->where('division_id', $divisionId))
            ->values();

        return view('tournament-registrations.index', [
            'activeDivisionId' => $activeDivisionId,
            'divisions' => $divisions,
            'registrationsByTournament' => $this->registrations->groupedByTournament($activeDivisionId),
            'tournaments' => $tournaments,
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

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
        $tournamentRegistration->load(['company', 'tournament.season', 'tournament.division', 'tournament.category', 'team']);

        if ($request->ajax()) {
            return view('tournament-registrations.partials.show', ['registration' => $tournamentRegistration]);
        }

        return view('tournament-registrations.show', ['registration' => $tournamentRegistration]);
    }

    public function edit(Request $request, TournamentRegistration $tournamentRegistration): View
    {
        $this->registrations->ensureVisible($tournamentRegistration);
        $tournamentRegistration->load(['tournament', 'team']);

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
