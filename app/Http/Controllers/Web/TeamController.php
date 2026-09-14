<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\ReviewTeamUpdateRequest;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\Company;
use App\Models\FixtureMatch;
use App\Models\MatchReportPlayer;
use App\Models\PlayerTransferRequest;
use App\Models\Team;
use App\Models\TeamUpdateRequest;
use App\Services\TeamService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function __construct(
        private readonly TeamService $teams
    ) {}

    public function index(): View
    {
        return view('teams.index', [
            'teams' => $this->teams->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('teams.partials.create-form', $data);
        }

        return view('teams.create', $data);
    }

    public function store(StoreTeamRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $team = $this->teams->create($request->validated());
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
                'message' => 'Equipo registrado correctamente.',
                'data' => ['id' => $team->id],
            ], 201);
        }

        return redirect()->route('teams.index')->with('success', 'Equipo registrado correctamente.');
    }

    public function show(Request $request, Team $team): View
    {
        $this->teams->ensureVisible($team);
        $team->load([
            'company',
            'pendingUpdateRequest.requester',
            'teamPlayers' => fn ($query) => $query
                ->with(['player', 'division'])
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('joined_at'),
            'registrations' => fn ($query) => $query
                ->with(['tournament.season', 'division', 'category'])
                ->orderByDesc('id'),
            'tournamentTeamPlayers' => fn ($query) => $query
                ->with(['player', 'tournament.season', 'tournamentRegistration.category'])
                ->orderByDesc('enabled_at'),
        ]);

        if ($request->ajax()) {
            return view('teams.partials.show', compact('team'));
        }

        $transfers = PlayerTransferRequest::query()
            ->with(['player', 'division', 'fromTeam', 'toTeam'])
            ->where('company_id', $team->company_id)
            ->where(fn ($query) => $query
                ->where('from_team_id', $team->id)
                ->orWhere('to_team_id', $team->id))
            ->orderByDesc('id')
            ->get();

        $matches = FixtureMatch::query()
            ->with(['tournament', 'category', 'homeTeam', 'awayTeam', 'matchdayDate.court', 'report'])
            ->where('company_id', $team->company_id)
            ->where(fn ($query) => $query
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id))
            ->orderByDesc('matchday_date_id')
            ->orderByDesc('match_number')
            ->get();

        $playerStats = MatchReportPlayer::query()
            ->where('company_id', $team->company_id)
            ->where('team_id', $team->id)
            ->selectRaw('COALESCE(SUM(goals), 0) as goals, COALESCE(SUM(yellow_cards), 0) as yellow_cards, COALESCE(SUM(red_cards), 0) as red_cards')
            ->first();

        $finishedMatches = $matches->filter(fn (FixtureMatch $match): bool => in_array($match->report?->status, ['completed', 'walkover'], true));
        $summary = [
            'players' => $team->teamPlayers->where('status', 'active')->count(),
            'tournaments' => $team->registrations->count(),
            'played' => $finishedMatches->count(),
            'pending' => $matches->count() - $finishedMatches->count(),
            'goals' => (int) ($playerStats?->goals ?? 0),
            'yellow_cards' => (int) ($playerStats?->yellow_cards ?? 0),
            'red_cards' => (int) ($playerStats?->red_cards ?? 0),
        ];

        return view('teams.show', compact('team', 'transfers', 'matches', 'summary'));
    }

    public function edit(Request $request, Team $team): View
    {
        $this->teams->ensureVisible($team);
        $team->load('pendingUpdateRequest');

        $data = $this->formData(['team' => $team]);

        if ($request->ajax()) {
            return view('teams.partials.edit-form', $data);
        }

        return view('teams.edit', $data);
    }

    public function update(UpdateTeamRequest $request, Team $team): JsonResponse|RedirectResponse
    {
        try {
            $result = $this->teams->update($team, $request->validated());
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

        $message = 'Solicitud de edicion enviada para aprobacion del superadmin.';

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => ['id' => $team->id],
            ]);
        }

        return redirect()->route('teams.index')->with('success', $message);
    }

    public function destroy(Team $team): RedirectResponse
    {
        $this->teams->delete($team);

        return redirect()->route('teams.index')->with('success', 'Equipo eliminado correctamente.');
    }

    public function matches(Request $request): JsonResponse
    {
        $matches = $this->teams->matches(
            $request->string('name')->toString(),
            $request->integer('company_id') ?: null,
            $request->integer('ignore_id') ?: null
        );

        return response()->json([
            'data' => $matches->map(fn (Team $team): array => [
                'id' => $team->id,
                'name' => $team->name,
                'founded_at' => $team->founded_at?->format('Y-m-d'),
            ])->values(),
        ]);
    }

    public function approvals(): View
    {
        return view('teams.approvals', [
            'requests' => $this->teams->pendingApprovals(),
        ]);
    }

    public function review(ReviewTeamUpdateRequest $request, TeamUpdateRequest $teamUpdateRequest): RedirectResponse
    {
        try {
            if ($request->validated('decision') === 'approve') {
                $this->teams->approve($teamUpdateRequest, $request->user()->id, $request->validated('review_notes'));

                return redirect()->route('teams.approvals')->with('success', 'Cambio de equipo aprobado correctamente.');
            }

            $this->teams->reject($teamUpdateRequest, $request->user()->id, $request->validated('review_notes'));

            return redirect()->route('teams.approvals')->with('success', 'Solicitud de cambio rechazada.');
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }
    }

    private function formData(array $data = []): array
    {
        return $data + [
            'companies' => Company::query()
                ->when(CompanyContext::id(), fn ($query, $companyId) => $query->whereKey($companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
