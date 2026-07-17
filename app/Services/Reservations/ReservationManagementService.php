<?php

namespace App\Services\Reservations;

use App\Models\Reservation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ReservationManagementService
{
    public function expireOverduePending(?int $companyId = null): int
    {
        $reservations = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['occupancyBlock', 'roomItems.occupancyBlock', 'bedUnitItems.occupancyBlock'])
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now())
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->get();

        foreach ($reservations as $reservation) {
            $this->expire($reservation);
        }

        return $reservations->count();
    }

    public function approve(Reservation $reservation, int $userId): Reservation
    {
        return DB::transaction(function () use ($reservation, $userId): Reservation {
            $reservation = $this->fresh($reservation);

            $reservation->update([
                'status' => 'confirmed',
                'payment_status' => 'validated',
                'payment_validated_at' => now(),
                'payment_validated_by' => $userId,
                'hold_expires_at' => null,
            ]);

            foreach ($this->blocks($reservation) as $block) {
                $block->restore();
                $block->update([
                    'status' => 'active',
                    'title' => 'Reserva '.$reservation->code.' confirmada',
                    'description' => 'Bloqueo definitivo generado por reserva confirmada.',
                ]);
            }

            return $reservation->refresh();
        });
    }

    public function reject(Reservation $reservation, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason): Reservation {
            $reservation = $this->fresh($reservation);

            $reservation->update([
                'status' => 'rejected',
                'payment_status' => 'rejected',
                'guest_notes' => $this->appendSystemNote($reservation->guest_notes, $reason ?: 'Pago rechazado por el establecimiento.'),
            ]);

            $this->releaseBlock($reservation);

            return $reservation->refresh();
        });
    }

    public function cancel(Reservation $reservation, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason): Reservation {
            $reservation = $this->fresh($reservation);

            $reservation->update([
                'status' => 'cancelled',
                'guest_notes' => $this->appendSystemNote($reservation->guest_notes, $reason ?: 'Reserva cancelada.'),
            ]);

            $this->releaseBlock($reservation);

            return $reservation->refresh();
        });
    }

    public function noShow(Reservation $reservation, ?string $reason = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $reason): Reservation {
            $reservation = $this->fresh($reservation);

            $reservation->update([
                'status' => 'no_show',
                'guest_notes' => $this->appendSystemNote($reservation->guest_notes, $reason ?: 'Reserva marcada como no show.'),
            ]);

            $this->releaseBlock($reservation);

            return $reservation->refresh();
        });
    }

    public function expire(Reservation $reservation): Reservation
    {
        return DB::transaction(function () use ($reservation): Reservation {
            $reservation = $this->fresh($reservation);

            if (! $reservation->hasExpiredTemporaryHold()) {
                return $reservation;
            }

            $reservation->update([
                'status' => 'expired',
            ]);

            $this->releaseBlock($reservation);

            return $reservation->refresh();
        });
    }

    private function fresh(Reservation $reservation): Reservation
    {
        return Reservation::query()
            ->withoutGlobalScope('company')
            ->with([
                'occupancyBlock' => fn ($query) => $query->withTrashed(),
                'roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
            ])
            ->whereKey($reservation->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function releaseBlock(Reservation $reservation): void
    {
        foreach ($this->blocks($reservation) as $block) {
            $block->update(['status' => 'cancelled']);
            $block->delete();
        }
    }

    private function blocks(Reservation $reservation)
    {
        return collect([$reservation->occupancyBlock])
            ->merge($reservation->roomItems->pluck('occupancyBlock'))
            ->merge($reservation->bedUnitItems->pluck('occupancyBlock'))
            ->filter()
            ->unique('id')
            ->values();
    }

    private function appendSystemNote(?string $current, string $note): string
    {
        return trim(collect([$current, '[Sistema] '.$note])->filter()->implode("\n\n"));
    }
}
