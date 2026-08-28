<?php

namespace App\Console\Commands;

use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Services\Reservations\ReservationGroupManagementService;
use App\Services\Reservations\ReservationManagementService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class MarkPastReservationsAsNoShow extends Command
{
    protected $signature = 'reservations:mark-no-shows';

    protected $description = 'Marca como no show las reservas que no llegaron en su fecha de ingreso';

    public function handle(
        ReservationGroupManagementService $groups,
        ReservationManagementService $reservations,
    ): int {
        $today = CarbonImmutable::today();
        $statuses = ['pending_payment', 'payment_under_review', 'confirmed'];
        $groupCount = 0;
        $reservationCount = 0;
        $reason = 'No show automatico: la fecha de ingreso vencio sin registrar check-in.';

        ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->whereIn('status', $statuses)
            ->whereDate('check_in', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($expiredGroups) use ($groups, $reason, &$groupCount): void {
                foreach ($expiredGroups as $group) {
                    $groups->noShow($group, $reason);
                    $groupCount++;
                }
            });

        Reservation::query()
            ->withoutGlobalScope('company')
            ->whereNull('reservation_group_id')
            ->whereIn('status', $statuses)
            ->whereDate('check_in', '<', $today->toDateString())
            ->orderBy('id')
            ->chunkById(100, function ($expiredReservations) use ($reservations, $reason, &$reservationCount): void {
                foreach ($expiredReservations as $reservation) {
                    $reservations->noShow($reservation, $reason);
                    $reservationCount++;
                }
            });

        $this->info("No show automatico: {$groupCount} grupos y {$reservationCount} reservas individuales.");

        return self::SUCCESS;
    }
}
