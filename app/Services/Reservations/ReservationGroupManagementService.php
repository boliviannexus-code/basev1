<?php

namespace App\Services\Reservations;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Services\CheckIn\AccountStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationGroupManagementService
{
    public function __construct(
        private readonly AccountStatementService $accountStatements,
    ) {}

    public function update(ReservationGroup $group, array $data): ReservationGroup
    {
        return DB::transaction(function () use ($group, $data): ReservationGroup {
            $group = $this->fresh($group);

            if ($group->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede modificar una reserva cancelada.',
                ]);
            }

            if ($group->status === 'checked_in') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede modificar una reserva enviada a check-in.',
                ]);
            }

            foreach ($data['reservations'] ?? [] as $reservationId => $reservationData) {
                $reservation = $group->reservations->firstWhere('id', (int) $reservationId);

                if (! $reservation) {
                    throw ValidationException::withMessages([
                        "reservations.{$reservationId}" => 'La reserva seleccionada no pertenece al grupo.',
                    ]);
                }

                $this->updateReservation($reservation, $reservationData);
            }

            return $this->syncGroupTotals($group);
        });
    }

    public function confirm(ReservationGroup $group, int $userId): ReservationGroup
    {
        return DB::transaction(function () use ($group, $userId): ReservationGroup {
            $group = $this->fresh($group);

            if ($group->status === 'checked_in') {
                throw ValidationException::withMessages([
                    'status' => 'Esta reserva ya fue enviada a check-in.',
                ]);
            }

            $group->update([
                'status' => 'confirmed',
                'payment_status' => 'validated',
            ]);

            foreach ($group->reservations as $reservation) {
                $reservation->update([
                    'status' => 'confirmed',
                    'payment_status' => 'validated',
                    'payment_validated_at' => now(),
                    'payment_validated_by' => $userId,
                    'hold_expires_at' => null,
                ]);

                foreach ($this->blocksForReservation($reservation) as $block) {
                    $block->restore();
                    $block->update([
                        'status' => 'active',
                        'title' => 'Reserva '.$group->code.' confirmada',
                        'description' => 'Bloqueo definitivo generado por reserva interna confirmada.',
                    ]);
                }
            }

            return $group->refresh();
        });
    }

    public function cancel(ReservationGroup $group, ?string $reason = null): ReservationGroup
    {
        return DB::transaction(function () use ($group, $reason): ReservationGroup {
            $group = $this->fresh($group);

            if ($group->status === 'checked_in' && ! $this->hasActiveBlocks($group)) {
                throw ValidationException::withMessages([
                    'status' => 'El check-in de esta reserva ya fue registrado.',
                ]);
            }

            $group->update([
                'status' => 'cancelled',
                'notes' => $this->appendSystemNote($group->notes, $reason ?: 'Reserva cancelada.'),
            ]);

            foreach ($group->reservations as $reservation) {
                $reservation->update([
                    'status' => 'cancelled',
                    'guest_notes' => $this->appendSystemNote($reservation->guest_notes, $reason ?: 'Reserva cancelada.'),
                ]);

                $this->releaseBlocks($reservation);
            }

            return $group->refresh();
        });
    }

    public function startCheckIn(ReservationGroup $group): ReservationGroup
    {
        return DB::transaction(function () use ($group): ReservationGroup {
            $group = $this->fresh($group);

            if ($group->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede hacer check-in de una reserva cancelada.',
                ]);
            }

            if (! $group->check_in->isSameDay(today())) {
                throw ValidationException::withMessages([
                    'check_in' => 'El check-in solo se puede iniciar en la fecha de ingreso de la reserva.',
                ]);
            }

            if ($group->status === 'checked_in') {
                if (! $this->hasActiveBlocks($group)) {
                    throw ValidationException::withMessages([
                        'status' => 'El check-in de esta reserva ya fue registrado.',
                    ]);
                }

                return $group->refresh()->load([
                    'reservationChannel',
                    'reservations.space',
                    'reservations.room',
                    'reservations.rooms',
                    'reservations.roomItems.room',
                    'reservations.bedUnitItems.bedUnit.room',
                    'accountStatement.items.extraChargeCategory',
                ]);
            }

            $group->update([
                'status' => 'checked_in',
                'notes' => $this->appendSystemNote($group->notes, 'Reserva enviada a check-in.'),
            ]);

            foreach ($group->reservations as $reservation) {
                $reservation->update([
                    'status' => 'checked_in',
                    'guest_notes' => $this->appendSystemNote($reservation->guest_notes, 'Reserva enviada a check-in.'),
                ]);
            }

            return $group->refresh()->load([
                'reservationChannel',
                'reservations.space',
                'reservations.room',
                'reservations.rooms',
                'reservations.roomItems.room',
                'reservations.bedUnitItems.bedUnit.room',
                'accountStatement.items.extraChargeCategory',
            ]);
        });
    }

    private function hasActiveBlocks(ReservationGroup $group): bool
    {
        return $group->reservations
            ->flatMap(fn (Reservation $reservation) => $this->blocksForReservation($reservation))
            ->contains(fn ($block): bool => $block->status === 'active' && ! $block->trashed());
    }

    private function fresh(ReservationGroup $group): ReservationGroup
    {
        return ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->with([
                'accountStatement.items',
                'reservations.space',
                'reservations.room',
                'reservations.roomItems.room',
                'reservations.bedUnitItems.bedUnit.room',
                'reservations.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservations.roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservations.bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
            ])
            ->whereKey($group->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function updateReservation(Reservation $reservation, array $data): Reservation
    {
        if ($reservation->status === 'cancelled') {
            throw ValidationException::withMessages([
                "reservations.{$reservation->id}" => 'No se puede modificar una reserva cancelada.',
            ]);
        }

        $reservation->loadMissing([
            'space',
            'room',
            'roomItems',
            'bedUnitItems.bedUnit',
            'reservationGroup.accountStatement',
        ]);

        $checkIn = CarbonImmutable::parse($reservation->check_in);
        $newCheckOut = CarbonImmutable::parse($data['check_out']);

        if ($newCheckOut->lte($checkIn)) {
            throw ValidationException::withMessages([
                "reservations.{$reservation->id}.check_out" => 'La fecha de salida debe ser posterior a la fecha de ingreso.',
            ]);
        }

        $oldNights = $this->nightDates($checkIn, CarbonImmutable::parse($reservation->check_out));
        $newNights = $this->nightDates($checkIn, $newCheckOut);
        $toAdd = array_values(array_diff($newNights, $oldNights));
        $toCancel = array_values(array_diff($oldNights, $newNights));
        $price = round((float) $data['price_per_night'], 2);

        if ($price < 0) {
            throw ValidationException::withMessages([
                "reservations.{$reservation->id}.price_per_night" => 'El precio no puede ser negativo.',
            ]);
        }

        if ($toAdd !== []) {
            $resource = $this->reservationResource($reservation);
            $this->ensureResourceAvailable(
                $reservation,
                $resource['space'],
                $resource['room'],
                $resource['bed_unit'],
                min($toAdd),
                CarbonImmutable::parse(max($toAdd))->addDay()->toDateString(),
            );
        }

        $subtotal = round($price * count($newNights), 2);
        $extrasTotal = round((float) $reservation->extraCharges()->where('status', 'active')->sum('total'), 2);
        $total = round($subtotal + $extrasTotal, 2);

        $reservation->update([
            'check_out' => $newCheckOut->toDateString(),
            'nights' => count($newNights),
            'price_per_person' => $price,
            'subtotal_amount' => $subtotal,
            'total_amount' => $total,
            'balance_amount' => max(round($total - (float) $reservation->advance_amount, 2), 0),
        ]);

        $this->updateReservationItems($reservation, $price, $subtotal);
        $this->updateBlocks($reservation, $newCheckOut);

        foreach ($toAdd as $date) {
            $this->accountStatements->addReservationNightCharge($reservation->refresh(), $date, $data['night_prices'][$date] ?? $price);
        }

        foreach ($toCancel as $date) {
            $this->accountStatements->cancelReservationNightCharge($reservation, $date);
        }

        $this->accountStatements->updateReservationNightlyRateFrom($reservation->refresh(), '0001-01-01', $data['night_prices'] ?? []);

        return $reservation->refresh();
    }

    private function syncGroupTotals(ReservationGroup $group): ReservationGroup
    {
        $group->refresh()->load(['reservations', 'accountStatement.items']);

        $statement = $group->accountStatement
            ? $this->accountStatements->recalculate($group->accountStatement)
            : null;
        $reservations = $group->reservations->where('status', '!=', 'cancelled');
        $checkIn = $reservations->min(fn (Reservation $reservation) => $reservation->check_in?->toDateString());
        $checkOut = $reservations->max(fn (Reservation $reservation) => $reservation->check_out?->toDateString());
        $nights = $checkIn && $checkOut
            ? CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut))
            : 0;

        $paymentStatus = $statement
            ? ($statement->status === 'paid' ? 'validated' : ($statement->status === 'partial' ? 'partial' : 'pending'))
            : $group->payment_status;

        $group->update([
            'check_in' => $checkIn ?: $group->check_in,
            'check_out' => $checkOut ?: $group->check_out,
            'nights' => $nights,
            'subtotal_amount' => round((float) $reservations->sum('subtotal_amount'), 2),
            'total_amount' => round((float) $reservations->sum('total_amount'), 2),
            'advance_amount' => $statement ? $statement->payments_total : $group->advance_amount,
            'balance_amount' => $statement ? $statement->balance : round((float) $reservations->sum('balance_amount'), 2),
            'payment_status' => $paymentStatus,
        ]);

        if ($statement) {
            $group->reservations()
                ->where('status', '!=', 'cancelled')
                ->update([
                    'payment_status' => $statement->status === 'paid'
                        ? 'validated'
                        : ($statement->payments_total > 0 ? 'submitted' : 'pending'),
                ]);
        }

        return $group->refresh()->load([
            'reservationChannel',
            'reservations.space',
            'reservations.room',
            'reservations.rooms',
            'reservations.roomItems.room',
            'reservations.bedUnitItems.bedUnit.room',
            'accountStatement.items.extraChargeCategory',
        ]);
    }

    private function updateReservationItems(Reservation $reservation, float $price, float $subtotal): void
    {
        $reservation->roomItems()->update([
            'price_per_night' => $price,
            'subtotal_amount' => $subtotal,
        ]);
        $reservation->bedUnitItems()->update([
            'price_per_night' => $price,
            'subtotal_amount' => $subtotal,
        ]);
    }

    private function updateBlocks(Reservation $reservation, CarbonImmutable $checkOut): void
    {
        $lastNight = $checkOut->subDay()->toDateString();

        foreach ($this->blocksForReservation($reservation) as $block) {
            $block->restore();
            $block->update([
                'status' => 'active',
                'start_date' => $reservation->check_in->toDateString(),
                'end_date' => $lastNight,
            ]);
        }
    }

    private function releaseBlocks(Reservation $reservation): void
    {
        foreach ($this->blocksForReservation($reservation) as $block) {
            $block->update(['status' => 'cancelled']);
            $block->delete();
        }
    }

    private function reservationResource(Reservation $reservation): array
    {
        $bedItem = $reservation->bedUnitItems->first();
        $roomItem = $reservation->roomItems->first();

        return [
            'space' => $reservation->space,
            'room' => $bedItem?->bedUnit?->room ?: $roomItem?->room ?: $reservation->room,
            'bed_unit' => $bedItem?->bedUnit,
        ];
    }

    private function ensureResourceAvailable(
        Reservation $reservation,
        Space $space,
        ?SpaceRoom $room,
        ?RoomBedUnit $bedUnit,
        string $checkIn,
        string $checkOut,
    ): void {
        if ($this->hasStay($space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasBlock($reservation, $space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasReservation($reservation, $space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasBlockingAvailability($space, $room, $bedUnit, $checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                "reservations.{$reservation->id}.check_out" => 'El recurso seleccionado no esta disponible para las noches adicionales.',
            ]);
        }
    }

    private function hasStay(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return Stay::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereIn('status', ['occupied'])
            ->whereDate('check_in_date', '<', $checkOut)
            ->whereDate('check_out_date', '>', $checkIn)
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested
                            ->where('space_room_id', $room?->id)
                            ->whereNull('room_bed_unit_id'));
                    return;
                }

                if ($room) {
                    $query->where('space_room_id', $room->id);
                    return;
                }

                $query->whereNull('space_room_id')->whereNull('room_bed_unit_id');
            })
            ->exists();
    }

    private function hasBlock(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();
        $excludedBlockIds = $this->blocksForReservation($reservation)->pluck('id')->all();

        return OccupancyBlock::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->when($excludedBlockIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $excludedBlockIds))
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested
                            ->where('space_room_id', $room?->id)
                            ->whereNull('room_bed_unit_id'))
                        ->orWhere(fn (Builder $nested): Builder => $nested
                            ->whereNull('space_room_id')
                            ->whereNull('room_bed_unit_id'));
                    return;
                }

                if ($room) {
                    $query->where(function (Builder $nested) use ($room): void {
                        $nested->where(fn (Builder $roomLevel): Builder => $roomLevel
                            ->where('space_room_id', $room->id)
                            ->whereNull('room_bed_unit_id'))
                            ->orWhere(fn (Builder $bedLevel): Builder => $bedLevel
                                ->where('space_room_id', $room->id)
                                ->whereNotNull('room_bed_unit_id'))
                            ->orWhere(fn (Builder $spaceLevel): Builder => $spaceLevel
                                ->whereNull('space_room_id')
                                ->whereNull('room_bed_unit_id'));
                    });
                    return;
                }

                $query->whereNull('space_room_id')->whereNull('room_bed_unit_id');
            })
            ->exists();
    }

    private function hasReservation(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return Reservation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereKeyNot($reservation->id)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->whereDate('check_in', '<', $checkOut)
            ->whereDate('check_out', '>', $checkIn)
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->whereHas('bedUnitItems', fn (Builder $item): Builder => $item->where('room_bed_unit_id', $bedUnit->id))
                        ->orWhere('space_room_id', $room?->id)
                        ->orWhereHas('roomItems', fn (Builder $item): Builder => $item->where('space_room_id', $room?->id));
                    return;
                }

                if ($room) {
                    $query->where('space_room_id', $room->id)
                        ->orWhereHas('roomItems', fn (Builder $item): Builder => $item->where('space_room_id', $room->id))
                        ->orWhereHas('bedUnitItems.bedUnit', fn (Builder $item): Builder => $item->where('space_room_id', $room->id));
                    return;
                }

                $query->whereNull('space_room_id')
                    ->whereDoesntHave('roomItems')
                    ->whereDoesntHave('bedUnitItems');
            })
            ->exists();
    }

    private function hasBlockingAvailability(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        $dates = $this->nightDates(CarbonImmutable::parse($checkIn), CarbonImmutable::parse($checkOut));

        return AvailabilityStatus::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereIn('date', $dates)
            ->whereIn('status', ['closed', 'reserved', 'occupied'])
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested
                            ->where('space_room_id', $room?->id)
                            ->whereNull('room_bed_unit_id'));
                    return;
                }

                if ($room) {
                    $query->where('space_room_id', $room->id);
                    return;
                }

                $query->whereNull('space_room_id')->whereNull('room_bed_unit_id');
            })
            ->exists();
    }

    private function nightDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        return collect(range(0, max($checkIn->diffInDays($checkOut) - 1, 0)))
            ->map(fn (int $offset): string => $checkIn->addDays($offset)->toDateString())
            ->all();
    }

    private function blocksForReservation($reservation)
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
