<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourtFee\StoreCourtFeeRequest;
use App\Http\Requests\CourtFee\UpdateCourtFeeRequest;
use App\Http\Requests\CourtFee\SyncCourtFeeTournamentsRequest;
use App\Models\CourtFee;
use App\Models\Tournament;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourtFeeController extends Controller
{
    public function index(): View
    {
        $fees = CompanyContext::scope(CourtFee::query())
            ->with(['tournaments' => fn ($query) => $query->orderBy('name')])
            ->withCount('items')->withSum('items', 'cost')->orderBy('name')->paginate(15);
        return view('court-fees.index', compact('fees'));
    }

    public function create(Request $request): View
    {
        if ($request->ajax()) {
            return view('court-fees.partials.create-form');
        }

        return view('court-fees.create');
    }

    public function store(StoreCourtFeeRequest $request): JsonResponse|RedirectResponse
    {
        $courtFee = CourtFee::query()->create($request->validated() + ['company_id' => CompanyContext::id($request->user())]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Derecho de cancha creado correctamente.',
                'data' => ['id' => $courtFee->id],
            ], 201);
        }

        return redirect()->route('court-fees.index')->with('success', 'Derecho de cancha creado correctamente.');
    }

    public function show(CourtFee $courtFee): View
    {
        $this->ensureVisible($courtFee);
        $courtFee->load(['items', 'tournaments.season'])->loadSum('items', 'cost');
        return view('court-fees.show', compact('courtFee'));
    }

    public function edit(Request $request, CourtFee $courtFee): View
    {
        $this->ensureVisible($courtFee);
        if ($request->ajax()) {
            return view('court-fees.partials.edit-form', compact('courtFee'));
        }
        return view('court-fees.edit', compact('courtFee'));
    }

    public function update(UpdateCourtFeeRequest $request, CourtFee $courtFee): JsonResponse|RedirectResponse
    {
        $courtFee->update($request->validated());
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Derecho de cancha actualizado correctamente.', 'data' => ['id' => $courtFee->id]]);
        }
        return redirect()->route('court-fees.show', $courtFee)->with('success', 'Derecho de cancha actualizado correctamente.');
    }

    public function destroy(CourtFee $courtFee): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.delete'), 403);
        $this->ensureVisible($courtFee);
        $courtFee->delete();
        return redirect()->route('court-fees.index')->with('success', 'Derecho de cancha eliminado correctamente.');
    }

    public function editTournaments(Request $request, CourtFee $courtFee): View
    {
        $this->ensureVisible($courtFee);
        $courtFee->load('tournaments:id');
        $tournaments = Tournament::query()->where('company_id', $courtFee->company_id)
            ->with('season:id,name')->orderByDesc('id')->get(['id', 'season_id', 'name', 'status']);

        return view('court-fees.partials.tournaments-form', compact('courtFee', 'tournaments'));
    }

    public function syncTournaments(SyncCourtFeeTournamentsRequest $request, CourtFee $courtFee): JsonResponse|RedirectResponse
    {
        $courtFee->tournaments()->sync($request->validated('tournament_ids', []));
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Torneos vinculados correctamente.']);
        }

        return redirect()->route('court-fees.show', $courtFee)->with('success', 'Torneos vinculados correctamente.');
    }

    private function ensureVisible(CourtFee $fee): void
    {
        abort_unless(CompanyContext::belongsToUser($fee->company_id, auth()->user()), 403);
    }
}
