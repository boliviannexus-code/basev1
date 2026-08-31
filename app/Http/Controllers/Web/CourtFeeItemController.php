<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourtFeeItem\StoreCourtFeeItemRequest;
use App\Http\Requests\CourtFeeItem\UpdateCourtFeeItemRequest;
use App\Models\CourtFeeItem;
use App\Models\CourtFee;
use App\Services\CourtFeeItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourtFeeItemController extends Controller
{
    public function __construct(private readonly CourtFeeItemService $items) {}

    public function create(Request $request, CourtFee $courtFee): View
    {
        abort_unless(\App\Support\CompanyContext::belongsToUser($courtFee->company_id, auth()->user()), 403);
        return view($request->ajax() ? 'court-fee-items.partials.create-form' : 'court-fee-items.create', compact('courtFee'));
    }

    public function store(StoreCourtFeeItemRequest $request, CourtFee $courtFee): JsonResponse|RedirectResponse
    {
        $item = $this->items->create($courtFee, $request->validated());
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Ítem registrado correctamente.', 'data' => ['id' => $item->id]], 201);
        }
        return redirect()->route('court-fees.show', $courtFee)->with('success', 'Ítem registrado correctamente.');
    }

    public function edit(Request $request, CourtFeeItem $courtFeeItem): View
    {
        $this->items->ensureVisible($courtFeeItem);
        $courtFeeItem->load('courtFee');
        return view($request->ajax() ? 'court-fee-items.partials.edit-form' : 'court-fee-items.edit', compact('courtFeeItem'));
    }

    public function update(UpdateCourtFeeItemRequest $request, CourtFeeItem $courtFeeItem): JsonResponse|RedirectResponse
    {
        $this->items->update($courtFeeItem, $request->validated());
        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Ítem actualizado correctamente.', 'data' => ['id' => $courtFeeItem->id]]);
        }
        return redirect()->route('court-fees.show', $courtFeeItem->court_fee_id)->with('success', 'Ítem actualizado correctamente.');
    }

    public function destroy(CourtFeeItem $courtFeeItem): RedirectResponse
    {
        abort_unless(auth()->user()?->can('court-fee-items.delete'), 403);
        $this->items->delete($courtFeeItem);
        return redirect()->route('court-fees.show', $courtFeeItem->court_fee_id)->with('success', 'Ítem eliminado correctamente.');
    }
}
