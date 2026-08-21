<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TournamentModification\SubstituteTeamRequest;
use App\Models\Team;
use App\Models\TournamentRegistration;
use App\Models\TournamentTeamSubstitution;
use App\Services\TournamentModificationService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TournamentModificationController extends Controller
{
    public function __construct(private readonly TournamentModificationService $modifications) {}

    public function index(): View
    {
        $companyId = CompanyContext::id();

        return view('tournament-modifications.index', [
            'substitutions' => CompanyContext::scope(TournamentTeamSubstitution::query())
                ->with(['tournament', 'registration.category', 'outgoingTeam', 'incomingTeam', 'creator'])
                ->latest('substituted_at')
                ->paginate(20),
            'registrations' => CompanyContext::scope(TournamentRegistration::query())
                ->with(['tournament.season', 'category', 'team'])
                ->where('status', 'registered')
                ->orderByDesc('tournament_id')
                ->orderBy('category_id')
                ->orderBy('series')
                ->orderBy('team_number')
                ->get(),
            'teams' => CompanyContext::scope(Team::query())
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function substitute(SubstituteTeamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $registration = TournamentRegistration::query()->findOrFail($data['tournament_registration_id']);
        $incomingTeam = Team::query()->findOrFail($data['incoming_team_id']);

        try {
            $substitution = $this->modifications->substitute($registration, $incomingTeam, $data['reason']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        return redirect()
            ->route('tournament-modifications.index')
            ->with('success', 'Sustitucion aplicada. Se actualizaron '.$substitution->fixture_matches_updated.' partido(s) del fixture.');
    }
}
