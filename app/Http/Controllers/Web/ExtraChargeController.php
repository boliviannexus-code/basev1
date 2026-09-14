<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtraCharge\StoreExtraChargeRequest;
use App\Http\Requests\ExtraCharge\SyncExtraChargeAssignmentsRequest;
use App\Http\Requests\ExtraCharge\UpdateExtraChargeRequest;
use App\Models\ExtraCharge;
use App\Models\Tournament;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExtraChargeController extends Controller
{
    public function index(): View
    {
        $charges = CompanyContext::scope(ExtraCharge::query())->with(['assignments.tournament:id,name', 'assignments.team:id,name'])->orderBy('name')->paginate(15);
        return view('extra-charges.index', compact('charges'));
    }
    public function create(Request $request): View { return view($request->ajax() ? 'extra-charges.partials.create-form' : 'extra-charges.create'); }
    public function store(StoreExtraChargeRequest $request): JsonResponse|RedirectResponse
    {
        $charge = ExtraCharge::query()->create($request->validated() + ['company_id' => CompanyContext::id($request->user())]);
        if ($request->ajax()) return response()->json(['success' => true, 'message' => 'Cargo extra creado correctamente.', 'data' => ['id' => $charge->id]], 201);
        return redirect()->route('extra-charges.index')->with('success', 'Cargo extra creado correctamente.');
    }
    public function edit(Request $request, ExtraCharge $extraCharge): View
    {
        $this->visible($extraCharge);
        return view($request->ajax() ? 'extra-charges.partials.edit-form' : 'extra-charges.edit', compact('extraCharge'));
    }
    public function update(UpdateExtraChargeRequest $request, ExtraCharge $extraCharge): JsonResponse|RedirectResponse
    {
        $extraCharge->update($request->validated());
        if ($request->ajax()) return response()->json(['success' => true, 'message' => 'Cargo extra actualizado correctamente.']);
        return redirect()->route('extra-charges.index')->with('success', 'Cargo extra actualizado correctamente.');
    }
    public function editAssignments(ExtraCharge $extraCharge): View
    {
        $this->visible($extraCharge);
        $extraCharge->load('assignments');
        $tournaments = Tournament::query()->where('company_id', $extraCharge->company_id)->with(['teams' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
        return view('extra-charges.partials.assignments-form', compact('extraCharge', 'tournaments'));
    }
    public function syncAssignments(SyncExtraChargeAssignmentsRequest $request, ExtraCharge $extraCharge): JsonResponse
    {
        $tournaments = Tournament::query()->where('company_id', $extraCharge->company_id)->with('teams:id')->get()->keyBy('id');
        DB::transaction(function () use ($request, $extraCharge, $tournaments): void {
            $extraCharge->assignments()->delete();
            foreach ($request->validated('assignments', []) as $tournamentId => $selection) {
                $tournament = $tournaments->get((int) $tournamentId);
                if (! $tournament) continue;
                if ((bool) ($selection['all'] ?? false)) {
                    $extraCharge->assignments()->create(['tournament_id' => $tournament->id, 'team_id' => null, 'all_teams' => true]);
                    continue;
                }
                $validTeamIds = $tournament->teams->modelKeys();
                foreach (array_intersect($selection['teams'] ?? [], $validTeamIds) as $teamId) {
                    $extraCharge->assignments()->create(['tournament_id' => $tournament->id, 'team_id' => $teamId, 'all_teams' => false]);
                }
            }
        });
        return response()->json(['success' => true, 'message' => 'Equipos asignados correctamente.']);
    }
    private function visible(ExtraCharge $charge): void { abort_unless(CompanyContext::belongsToUser($charge->company_id, auth()->user()), 403); }
}
