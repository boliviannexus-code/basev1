<?php

namespace App\Services\Reports;

use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Stay;
use Illuminate\Support\Collection;

class ProjectedCollectionsService
{
    public function build(int $companyId): Collection
    {
        $stays = Stay::query()->where('company_id', $companyId)->where('status', 'occupied')
            ->whereHas('accountStatement', fn ($query) => $query->where('balance', '>', 0))
            ->with(['accountStatement', 'holderGuest', 'space', 'room'])->get()
            ->map(fn (Stay $stay): array => [
                'type' => 'Estancia', 'date' => $stay->check_out_date,
                'guest' => $stay->holderGuest?->full_name ?? '-',
                'reference' => $stay->room?->name ?? $stay->space?->name ?? '-',
                'balance' => (float) $stay->accountStatement->balance, 'currency' => $stay->accountStatement->currency,
            ]);

        $groups = ReservationGroup::query()->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)->with('accountStatement')->get()
            ->map(fn (ReservationGroup $group): array => [
                'type' => 'Reserva', 'date' => $group->check_in, 'guest' => $group->guest_name,
                'reference' => $group->code, 'balance' => (float) ($group->accountStatement?->balance ?? $group->balance_amount),
                'currency' => $group->accountStatement?->currency ?? $group->currency,
            ]);

        $reservations = Reservation::query()->where('company_id', $companyId)->whereNull('reservation_group_id')
            ->whereIn('status', Reservation::BLOCKING_STATUSES)->with('accountStatement')->get()
            ->map(fn (Reservation $reservation): array => [
                'type' => 'Reserva', 'date' => $reservation->check_in, 'guest' => $reservation->guest_name,
                'reference' => $reservation->code, 'balance' => (float) ($reservation->accountStatement?->balance ?? $reservation->balance_amount),
                'currency' => $reservation->accountStatement?->currency ?? $reservation->currency,
            ]);

        return $stays->concat($groups)->concat($reservations)->filter(fn (array $row): bool => $row['balance'] > 0)
            ->sortBy(fn (array $row): string => $row['date']?->toDateString() ?? '9999-12-31')->values();
    }
}
