<?php

namespace App\Services;

use App\Models\AccountStatementItem;
use App\Models\ExtraChargeCategory;
use App\Models\Reservation;
use App\Models\ReservationExtraCharge;
use App\Models\Stay;
use App\Services\CheckIn\AccountStatementService;
use Illuminate\Support\Facades\DB;

class ExtraChargeService
{
    public function __construct(
        private readonly AccountStatementService $accountStatements,
    ) {}

    public function addToStay(Stay $stay, ExtraChargeCategory $category, array $data): AccountStatementItem
    {
        return DB::transaction(fn (): AccountStatementItem => $this->accountStatements->addExtraCharge($stay, $category, $data));
    }

    public function cancelStayCharge(AccountStatementItem $item): void
    {
        DB::transaction(fn (): mixed => tap(null, fn () => $this->accountStatements->cancelExtraCharge($item)));
    }

    public function addToReservation(Reservation $reservation, ExtraChargeCategory $category, array $data): ReservationExtraCharge
    {
        return DB::transaction(function () use ($reservation, $category, $data): ReservationExtraCharge {
            $quantity = round((float) $data['quantity'], 2);
            $unitPrice = round((float) $data['unit_price'], 2);
            $detail = trim((string) ($data['detail'] ?? '')) ?: $category->name;

            $charge = ReservationExtraCharge::query()->create([
                'company_id' => $reservation->company_id,
                'reservation_id' => $reservation->id,
                'extra_charge_category_id' => $category->id,
                'date' => $data['date'] ?? now()->toDateString(),
                'detail' => $detail,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($quantity * $unitPrice, 2),
                'currency' => 'BOB',
                'status' => 'active',
            ]);

            $this->recalculateReservation($reservation);

            return $charge;
        });
    }

    public function cancelReservationCharge(ReservationExtraCharge $charge): void
    {
        DB::transaction(function () use ($charge): void {
            if ($charge->status === 'active') {
                $charge->update(['status' => 'cancelled']);
                $this->recalculateReservation($charge->reservation);
            }
        });
    }

    public function recalculateReservation(Reservation $reservation): Reservation
    {
        $extrasTotal = (float) $reservation->extraCharges()
            ->where('status', 'active')
            ->sum('total');
        $baseTotal = (float) $reservation->subtotal_amount
            + (float) ($reservation->extra_people_total ?? 0)
            + (float) ($reservation->package_extra_nights_total ?? 0);
        $total = round($baseTotal + $extrasTotal, 2);
        $deposit = (float) ($reservation->deposit_amount ?? $reservation->advance_amount ?? 0);

        $reservation->update([
            'total_amount' => $total,
            'balance_amount' => round($total - $deposit, 2),
        ]);

        return $reservation->refresh();
    }

    public function transferReservationChargesToStay(Reservation $reservation, Stay $stay): void
    {
        $reservation->loadMissing('extraCharges.category');

        foreach ($reservation->extraCharges->where('status', 'active') as $charge) {
            if (! $charge->category) {
                continue;
            }

            $this->accountStatements->addExtraCharge($stay, $charge->category, [
                'date' => $charge->date?->toDateString() ?? now()->toDateString(),
                'detail' => $charge->detail,
                'quantity' => $charge->quantity,
                'unit_price' => $charge->unit_price,
            ]);
        }
    }
}
