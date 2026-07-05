<?php

namespace App\Services\Reservations;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\Reservation;
use App\Models\ReservationBedUnit;
use App\Models\ReservationGroup;
use App\Models\ReservationRoom;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationMoveService
{
    public function __construct(
        private readonly AccountStatementService $accounts,
    ) {}

    public function availableResources(Reservation $reservation, string $checkIn, string $checkOut): array
    {
        $start = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($start->lt(today()) || $end->lte($start)) {
            return [];
        }

        return Space::query()
            ->with([
                'spaceMode',
                'rooms' => fn ($query) => $query
                    ->with(['beds.bedType', 'bedUnits.bedType'])
                    ->where('status', 'active')
                    ->orderBy('name'),
            ])
            ->where('company_id', $reservation->company_id)
            ->where('status', 'active')
            ->whereHas('spaceMode', fn (Builder $query): Builder => $query->whereIn('slug', ['privado', 'compartido']))
            ->orderByRaw('coalesce(title, name) asc')
            ->get()
            ->flatMap(fn (Space $space): array => $this->resourceOptionsForSpace($reservation, $space, $checkIn, $checkOut))
            ->values()
            ->all();
    }

    public function move(Reservation $reservation, array $data, ?User $user = null): Reservation
    {
        return DB::transaction(function () use ($reservation, $data, $user): Reservation {
            $reservation->refresh()->loadMissing([
                'reservationGroup.accountStatement.items',
                'occupancyBlock' => fn ($query) => $query->withTrashed(),
                'roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
            ]);

            if (! $reservation->shouldBlockAvailability()) {
                throw ValidationException::withMessages([
                    'status' => 'Solo se pueden mover reservas pendientes, en revision o confirmadas.',
                ]);
            }

            $checkIn = CarbonImmutable::parse($data['check_in'])->startOfDay();
            $checkOut = CarbonImmutable::parse($data['check_out'])->startOfDay();

            if ($checkIn->lt(today())) {
                throw ValidationException::withMessages([
                    'check_in' => 'La fecha de ingreso no puede ser anterior a hoy.',
                ]);
            }

            if ($checkOut->lte($checkIn)) {
                throw ValidationException::withMessages([
                    'check_out' => 'La fecha de salida debe ser posterior al ingreso.',
                ]);
            }

            [$space, $room, $bedUnit] = $this->resolveResource($reservation, $data);
            $this->ensureResourceType((string) $data['resource_type'], $space, $room, $bedUnit);
            $this->ensureCapacity((int) $reservation->guests, $space, $room, $bedUnit);
            $this->ensureResourceAvailable($reservation, $space, $room, $bedUnit, $checkIn->toDateString(), $checkOut->toDateString());

            $oldDates = $this->nightDates(
                CarbonImmutable::parse($reservation->check_in),
                CarbonImmutable::parse($reservation->check_out),
            );
            $newDates = $this->nightDates($checkIn, $checkOut);
            $price = round((float) $data['price_per_night'], 2);
            $subtotal = round($price * count($newDates), 2);
            $extrasTotal = round((float) $reservation->extraCharges()->where('status', 'active')->sum('total'), 2);
            $total = round($subtotal + $extrasTotal, 2);

            $reservation->update([
                'space_id' => $space->id,
                'space_room_id' => $room && ! $bedUnit ? $room->id : null,
                'check_in' => $checkIn->toDateString(),
                'check_out' => $checkOut->toDateString(),
                'nights' => count($newDates),
                'price_per_person' => $price,
                'subtotal_amount' => $subtotal,
                'total_amount' => $total,
                'balance_amount' => max(round($total - (float) $reservation->advance_amount, 2), 0),
                'guest_notes' => $this->appendSystemNote($reservation->guest_notes, $data['notes'] ?? null),
            ]);

            $this->syncReservationItems($reservation->refresh(), $space, $room, $bedUnit, $price, $subtotal);
            $this->syncBlocks($reservation->refresh(), $space, $room, $bedUnit, $checkIn, $checkOut, $user);
            $this->syncReservationCharges($reservation->refresh(), $oldDates, $newDates, $price);
            $this->syncGroupTotals($reservation->reservationGroup);

            return $reservation->refresh()->load([
                'space',
                'room',
                'roomItems.room',
                'bedUnitItems.bedUnit.room',
                'reservationGroup.accountStatement.items',
            ]);
        });
    }

    private function resourceOptionsForSpace(Reservation $reservation, Space $space, string $checkIn, string $checkOut): array
    {
        if ($space->spaceMode?->slug !== 'compartido') {
            return [$this->resourcePayload($reservation, $space, null, null, $checkIn, $checkOut)];
        }

        return $space->rooms
            ->flatMap(function (SpaceRoom $room) use ($reservation, $space, $checkIn, $checkOut): array {
                $resources = [];

                if (in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                    $resources[] = $this->resourcePayload($reservation, $space, $room, null, $checkIn, $checkOut);
                }

                if (in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                    foreach ($room->bedUnits->where('status', 'active') as $bedUnit) {
                        $resources[] = $this->resourcePayload($reservation, $space, $room, $bedUnit, $checkIn, $checkOut);
                    }
                }

                return $resources;
            })
            ->all();
    }

    private function resourcePayload(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): array
    {
        $available = ! $this->resourceHasConflict($reservation, $space, $room, $bedUnit, $checkIn, $checkOut);
        $label = collect([
            $space->spaceMode?->slug === 'compartido' ? ($space->name ?: $space->title) : ($space->title ?: $space->name),
            $room ? ($room->name ?: $room->title) : null,
            $bedUnit?->label,
        ])->filter()->implode(' / ');

        return [
            'key' => $bedUnit ? 'bed:'.$bedUnit->id : (($room ? 'room:' : 'space:').($room?->id ?? $space->id)),
            'resource_type' => $bedUnit ? 'shared_bed_unit' : ($room ? 'shared_room' : 'private_space'),
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'label' => $label,
            'capacity' => $this->capacity($space, $room, $bedUnit),
            'available' => $available,
            'disabled' => ! $available,
            'status' => $available ? 'available' : 'closed',
            'sale_mode' => $room?->sale_mode,
        ];
    }

    private function resolveResource(Reservation $reservation, array $data): array
    {
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $reservation->company_id)
            ->where('status', 'active')
            ->whereKey($data['space_id'])
            ->first();

        if (! $space) {
            throw ValidationException::withMessages(['space_id' => 'El espacio seleccionado no esta disponible.']);
        }

        if ($data['resource_type'] === 'private_space') {
            return [$space, null, null];
        }

        $room = SpaceRoom::query()
            ->with(['beds', 'bedUnits.bedType'])
            ->where('company_id', $reservation->company_id)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereKey($data['space_room_id'] ?? null)
            ->first();

        if (! $room) {
            throw ValidationException::withMessages(['space_room_id' => 'Selecciona una habitacion valida.']);
        }

        if ($data['resource_type'] === 'shared_room') {
            return [$space, $room, null];
        }

        $bedUnit = RoomBedUnit::query()
            ->with('bedType')
            ->where('company_id', $reservation->company_id)
            ->where('space_room_id', $room->id)
            ->where('status', 'active')
            ->whereKey($data['room_bed_unit_id'] ?? null)
            ->first();

        if (! $bedUnit) {
            throw ValidationException::withMessages(['room_bed_unit_id' => 'Selecciona una cama valida.']);
        }

        return [$space, $room, $bedUnit];
    }

    private function ensureResourceType(string $resourceType, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        if ($resourceType === 'private_space' && ($space->spaceMode?->slug === 'compartido' || $room || $bedUnit)) {
            throw ValidationException::withMessages(['resource_type' => 'El recurso seleccionado no es un alojamiento privado.']);
        }

        if ($resourceType === 'shared_room' && (! $room || $bedUnit || ! in_array($room->sale_mode, ['full_room', 'flexible'], true))) {
            throw ValidationException::withMessages(['resource_type' => 'La habitacion seleccionada no permite venta completa.']);
        }

        if ($resourceType === 'shared_bed_unit' && (! $room || ! $bedUnit || ! in_array($room->sale_mode, ['bed_unit', 'flexible'], true))) {
            throw ValidationException::withMessages(['resource_type' => 'La cama seleccionada no esta disponible para venta por cama.']);
        }
    }

    private function ensureCapacity(int $guests, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        $capacity = $this->capacity($space, $room, $bedUnit);

        if ($guests > $capacity) {
            throw ValidationException::withMessages([
                'space_id' => "La capacidad maxima de este recurso es {$capacity}.",
            ]);
        }
    }

    private function ensureResourceAvailable(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): void
    {
        if ($this->resourceHasConflict($reservation, $space, $room, $bedUnit, $checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                'space_id' => 'El recurso seleccionado no esta disponible en esas fechas.',
            ]);
        }
    }

    private function resourceHasConflict(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return $this->hasStay($space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasBlock($reservation, $space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasReservation($reservation, $space, $room, $bedUnit, $checkIn, $checkOut)
            || $this->hasBlockingAvailability($space, $room, $bedUnit, $checkIn, $checkOut);
    }

    private function hasStay(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return Stay::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('status', 'occupied')
            ->whereDate('check_in_date', '<', $checkOut)
            ->whereDate('check_out_date', '>', $checkIn)
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested->where('space_room_id', $room?->id)->whereNull('room_bed_unit_id'));

                    return;
                }

                if ($room) {
                    $query->where('space_room_id', $room->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested->where('space_room_id', $room->id)->whereNotNull('room_bed_unit_id'));

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
                        ->orWhere(fn (Builder $nested): Builder => $nested->where('space_room_id', $room?->id)->whereNull('room_bed_unit_id'))
                        ->orWhere(fn (Builder $nested): Builder => $nested->whereNull('space_room_id')->whereNull('room_bed_unit_id'));

                    return;
                }

                if ($room) {
                    $query->where(fn (Builder $nested): Builder => $nested
                        ->where(fn (Builder $roomLevel): Builder => $roomLevel->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'))
                        ->orWhere(fn (Builder $bedLevel): Builder => $bedLevel->where('space_room_id', $room->id)->whereNotNull('room_bed_unit_id'))
                        ->orWhere(fn (Builder $spaceLevel): Builder => $spaceLevel->whereNull('space_room_id')->whereNull('room_bed_unit_id')));

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
            ->where(function (Builder $query): void {
                $query->where('status', '!=', 'pending_payment')
                    ->orWhereNull('hold_expires_at')
                    ->orWhere('hold_expires_at', '>', now());
            })
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

                $query->whereNull('space_room_id')->whereDoesntHave('roomItems')->whereDoesntHave('bedUnitItems');
            })
            ->exists();
    }

    private function hasBlockingAvailability(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return AvailabilityStatus::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereIn('date', $this->nightDates(CarbonImmutable::parse($checkIn), CarbonImmutable::parse($checkOut)))
            ->whereIn('status', ['closed', 'reserved', 'occupied'])
            ->where(function (Builder $query) use ($room, $bedUnit): void {
                if ($bedUnit) {
                    $query->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $nested): Builder => $nested->where('space_room_id', $room?->id)->whereNull('room_bed_unit_id'));

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

    private function syncReservationItems(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, float $price, float $subtotal): void
    {
        $reservation->roomItems()->delete();
        $reservation->bedUnitItems()->delete();

        if ($bedUnit) {
            ReservationBedUnit::query()->create([
                'reservation_id' => $reservation->id,
                'room_bed_unit_id' => $bedUnit->id,
                'occupancy_block_id' => $this->primaryBlock($reservation)?->id,
                'guest_name' => $reservation->guest_name,
                'price_per_night' => $price,
                'subtotal_amount' => $subtotal,
            ]);

            return;
        }

        if ($room) {
            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'space_room_id' => $room->id,
                'occupancy_block_id' => $this->primaryBlock($reservation)?->id,
                'capacity' => $this->capacity($space, $room, null),
                'price_per_night' => $price,
                'subtotal_amount' => $subtotal,
            ]);
        }
    }

    private function syncBlocks(Reservation $reservation, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, CarbonImmutable $checkIn, CarbonImmutable $checkOut, ?User $user): void
    {
        $blocks = $this->blocksForReservation($reservation);
        $primary = $blocks->first();
        $lastNight = $checkOut->subDay()->toDateString();

        if (! $primary) {
            $primary = OccupancyBlock::query()->create([
                'company_id' => $reservation->company_id,
                'space_id' => $space->id,
                'space_room_id' => $room?->id,
                'room_bed_unit_id' => $bedUnit?->id,
                'type' => 'unavailable',
                'status' => 'active',
                'title' => 'Reserva '.$reservation->code,
                'description' => 'Bloqueo generado desde movimiento de reserva.',
                'start_date' => $checkIn->toDateString(),
                'end_date' => $lastNight,
                'created_by' => $user?->id,
            ]);

            $reservation->update(['occupancy_block_id' => $primary->id]);
        }

        $primary->restore();
        $primary->update([
            'company_id' => $reservation->company_id,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'type' => 'unavailable',
            'status' => 'active',
            'title' => 'Reserva '.$reservation->code,
            'start_date' => $checkIn->toDateString(),
            'end_date' => $lastNight,
        ]);

        $reservation->update(['occupancy_block_id' => $primary->id]);
        $reservation->roomItems()->update(['occupancy_block_id' => $primary->id]);
        $reservation->bedUnitItems()->update(['occupancy_block_id' => $primary->id]);

        $blocks->where('id', '!=', $primary->id)->each(function (OccupancyBlock $block): void {
            $block->update(['status' => 'cancelled']);
            $block->delete();
        });
    }

    private function syncReservationCharges(Reservation $reservation, array $oldDates, array $newDates, float $price): void
    {
        foreach (array_diff($oldDates, $newDates) as $date) {
            $this->accounts->cancelReservationNightCharge($reservation, $date);
        }

        foreach ($newDates as $date) {
            $this->accounts->addReservationNightCharge($reservation, $date, $price);
        }

        $this->accounts->updateReservationNightlyRateFrom($reservation, min($newDates), []);
    }

    private function syncGroupTotals(?ReservationGroup $group): void
    {
        if (! $group) {
            return;
        }

        $group->refresh()->load(['reservations', 'accountStatement.items']);
        $reservations = $group->reservations->where('status', '!=', 'cancelled');
        $checkIn = $reservations->min(fn (Reservation $reservation) => $reservation->check_in?->toDateString());
        $checkOut = $reservations->max(fn (Reservation $reservation) => $reservation->check_out?->toDateString());
        $statement = $group->accountStatement ? $this->accounts->recalculate($group->accountStatement) : null;

        $group->update([
            'check_in' => $checkIn ?: $group->check_in,
            'check_out' => $checkOut ?: $group->check_out,
            'nights' => $checkIn && $checkOut ? CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut)) : 0,
            'subtotal_amount' => round((float) $reservations->sum('subtotal_amount'), 2),
            'total_amount' => round((float) $reservations->sum('total_amount'), 2),
            'advance_amount' => $statement ? $statement->payments_total : $group->advance_amount,
            'balance_amount' => $statement ? $statement->balance : round((float) $reservations->sum('balance_amount'), 2),
            'payment_status' => $statement
                ? ($statement->status === 'paid' ? 'validated' : ($statement->status === 'partial' ? 'partial' : 'pending'))
                : $group->payment_status,
        ]);
    }

    private function capacity(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): int
    {
        if ($bedUnit) {
            return max((int) ($bedUnit->bedType?->capacity ?: 1), 1);
        }

        if ($room) {
            return max((int) ($room->max_capacity ?: 0) ?: (int) $room->beds->sum('total_capacity') ?: (int) $room->bedUnits->where('status', 'active')->count(), 1);
        }

        return max((int) ($space->max_capacity ?: 1), 1);
    }

    private function blocksForReservation(Reservation $reservation)
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
            ->values();
    }

    private function primaryBlock(Reservation $reservation): ?OccupancyBlock
    {
        return $this->blocksForReservation($reservation)->first();
    }

    private function nightDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        $lastNight = $checkOut->subDay();

        if ($lastNight->lt($checkIn)) {
            return [];
        }

        return collect(CarbonPeriod::create($checkIn, $lastNight))
            ->map(fn ($date): string => $date->toDateString())
            ->all();
    }

    private function appendSystemNote(?string $current, ?string $note): ?string
    {
        if (! filled($note)) {
            return $current;
        }

        return trim(collect([$current, '[Sistema] Cambio de habitacion: '.$note])->filter()->implode("\n\n"));
    }
}
