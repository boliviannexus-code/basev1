<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AccountStatementItem;
use App\Models\ExtraChargeCategory;
use App\Models\Reservation;
use App\Models\ReservationExtraCharge;
use App\Models\Stay;
use App\Services\ExtraChargeService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExtraChargeController extends Controller
{
    public function __construct(
        private readonly ExtraChargeService $extraCharges,
    ) {}

    public function stayForm(Stay $stay): View
    {
        $this->ensureStayOwnership($stay);

        return view('extra-charges.partials.form', [
            'targetType' => 'stay',
            'target' => $stay,
            'action' => route('stays.extra-charges.store', $stay),
            'categories' => $this->categories(),
        ]);
    }

    public function reservationForm(Reservation $reservation): View
    {
        $this->ensureReservationOwnership($reservation);
        $this->ensureReservationCanReceiveCharges($reservation);

        return view('extra-charges.partials.form', [
            'targetType' => 'reservation',
            'target' => $reservation,
            'action' => route('admin.reservations.extra-charges.store', $reservation),
            'categories' => $this->categories(),
        ]);
    }

    public function storeForStay(Request $request, Stay $stay): RedirectResponse
    {
        $this->ensureStayOwnership($stay);
        abort_unless($stay->status === 'occupied', 403);

        $data = $this->validated($request);
        $category = $this->category((int) $data['extra_charge_category_id']);

        $this->extraCharges->addToStay($stay, $category, $data);

        return back()->with('success', 'Cargo extra registrado correctamente.');
    }

    public function storeForReservation(Request $request, Reservation $reservation): RedirectResponse
    {
        $this->ensureReservationOwnership($reservation);
        $this->ensureReservationCanReceiveCharges($reservation);

        $data = $this->validated($request);
        $category = $this->category((int) $data['extra_charge_category_id']);

        $this->extraCharges->addToReservation($reservation, $category, $data);

        return back()->with('success', 'Cargo extra registrado correctamente.');
    }

    public function cancelStayCharge(AccountStatementItem $item): RedirectResponse
    {
        abort_unless((int) $item->company_id === $this->companyId(), 404);
        $this->extraCharges->cancelStayCharge($item);

        return back()->with('success', 'Cargo extra cancelado correctamente.');
    }

    public function cancelReservationCharge(ReservationExtraCharge $charge): RedirectResponse
    {
        abort_unless((int) $charge->company_id === $this->companyId(), 404);
        $this->extraCharges->cancelReservationCharge($charge);

        return back()->with('success', 'Cargo extra cancelado correctamente.');
    }

    private function validated(Request $request): array
    {
        $companyId = $this->companyId();

        return $request->validate([
            'extra_charge_category_id' => [
                'required',
                Rule::exists((new ExtraChargeCategory)->getTable(), 'id')
                    ->where(fn (QueryBuilder $query): QueryBuilder => $query
                        ->where('company_id', $companyId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')),
            ],
            'date' => ['nullable', 'date'],
            'detail' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.5', 'multiple_of:0.5'],
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function categories()
    {
        ExtraChargeCategory::ensureDefaultsForCompany($this->companyId());

        return ExtraChargeCategory::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private function category(int $id): ExtraChargeCategory
    {
        return ExtraChargeCategory::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->findOrFail($id);
    }

    private function ensureStayOwnership(Stay $stay): void
    {
        abort_unless((int) $stay->company_id === $this->companyId(), 404);
    }

    private function ensureReservationOwnership(Reservation $reservation): void
    {
        abort_unless((int) $reservation->company_id === $this->companyId(), 404);
    }

    private function ensureReservationCanReceiveCharges(Reservation $reservation): void
    {
        abort_if(in_array($reservation->status, ['cancelled', 'rejected', 'expired', 'no_show'], true), 403);

        if ($reservation->status === 'checked_in') {
            abort_unless($this->reservationHasActiveBlocks($reservation), 403);
        }
    }

    private function reservationHasActiveBlocks(Reservation $reservation): bool
    {
        $reservation->loadMissing([
            'occupancyBlock' => fn ($query) => $query->withTrashed(),
            'roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
            'bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
        ]);

        return collect([$reservation->occupancyBlock])
            ->merge($reservation->roomItems->pluck('occupancyBlock'))
            ->merge($reservation->bedUnitItems->pluck('occupancyBlock'))
            ->filter()
            ->unique('id')
            ->contains(fn ($block): bool => $block->status === 'active' && ! $block->trashed());
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
