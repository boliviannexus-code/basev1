<?php

namespace App\Services\Availability;

use App\Models\AvailabilityDay;
use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AvailabilityStatusService
{
    public function validateRangeAvailable(int $companyId, array $data): array
    {
        [$space, $room, $bedUnit] = $this->validateResource($companyId, $data['space_id'], $data['space_room_id'] ?? null, $data['room_bed_unit_id'] ?? null);
        $checkIn = Carbon::parse($data['check_in_date'])->startOfDay();
        $checkOut = Carbon::parse($data['check_out_date'])->startOfDay();
        $lastNight = $checkOut->copy()->subDay();

        if ($checkOut->lte($checkIn)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'La fecha de salida debe ser posterior a la fecha de ingreso.',
            ]);
        }

        if ($this->hasActiveOccupancyBlockInRange($companyId, $space->id, $room?->id, $bedUnit?->id, $checkIn->toDateString(), $lastNight->toDateString(), $data['reservation_group_id'] ?? null)) {
            throw ValidationException::withMessages([
                'stays' => 'El recurso ya tiene un bloqueo u ocupacion en esas fechas.',
            ]);
        }

        $statuses = $this->statusesForRange($companyId, $space, $room, $bedUnit, $checkIn->toDateString(), $lastNight->toDateString());

        if ($statuses->contains(fn (AvailabilityStatus $status): bool => in_array($status->status, ['closed', 'occupied'], true))) {
            throw ValidationException::withMessages([
                'stays' => 'No se puede hacer check-in sobre un recurso cerrado u ocupado.',
            ]);
        }

        if ($statuses->contains(fn (AvailabilityStatus $status): bool => $status->status === 'reserved') && ! (bool) ($data['confirm_reserved_conversion'] ?? false)) {
            throw ValidationException::withMessages([
                'confirm_reserved_conversion' => 'Confirma la conversion de reservado a ocupado para continuar.',
            ]);
        }

        return [$space, $room, $bedUnit];
    }

    public function markRangeOccupied(int $companyId, Stay $stay, ?User $user = null): void
    {
        $checkIn = Carbon::parse($stay->check_in_date)->startOfDay();
        $checkOut = Carbon::parse($stay->check_out_date)->startOfDay();
        $lastNight = $checkOut->copy()->subDay();

        foreach ($checkIn->daysUntil($lastNight) as $date) {
            $this->upsertOccupiedStatus($companyId, $stay, $date->toDateString(), $user);
        }
    }

    public function validateAvailableRange(Stay $stay, array $dates): void
    {
        if ($dates === []) {
            return;
        }

        $first = min($dates);
        $last = max($dates);

        if ($this->hasActiveOccupancyBlockInRange((int) $stay->company_id, (int) $stay->space_id, $stay->space_room_id, $stay->room_bed_unit_id, $first, $last)) {
            throw ValidationException::withMessages([
                'check_out_date' => 'No se puede extender la estancia sobre fechas bloqueadas o reservadas.',
            ]);
        }

        $statuses = $this->statusesForRange($stay->company_id, $stay->space, $stay->room, $stay->bedUnit, $first, $last)
            ->filter(fn (AvailabilityStatus $status): bool => in_array($status->date->toDateString(), $dates, true));

        if ($statuses->contains(fn (AvailabilityStatus $status): bool => in_array($status->status, ['closed', 'occupied', 'reserved'], true))) {
            throw ValidationException::withMessages([
                'check_out_date' => 'No se puede extender la estancia sobre fechas cerradas, ocupadas o reservadas.',
            ]);
        }
    }

    public function markOccupiedRange(Stay $stay, array $dates, ?User $user = null): void
    {
        foreach ($dates as $date) {
            $this->upsertOccupiedStatus((int) $stay->company_id, $stay, $date, $user);
        }
    }

    public function releaseOccupiedRange(Stay $stay, array $dates): void
    {
        foreach ($dates as $date) {
            $existing = $this->existingStatus((int) $stay->company_id, (int) $stay->space_id, $stay->space_room_id, $stay->room_bed_unit_id, $date);

            if ($existing && $existing->status === 'occupied' && $existing->source === 'check_in') {
                $existing->delete();
            }
        }
    }

    public function changeStatus(int $companyId, array $data, ?AvailabilityStatus $availabilityStatus = null, ?User $user = null): ?AvailabilityStatus
    {
        [$space, $room, $bedUnit] = $this->validateResource($companyId, $data['space_id'], $data['space_room_id'] ?? null, $data['room_bed_unit_id'] ?? null);
        $date = Carbon::parse($data['date'])->toDateString();
        $status = $data['status'];
        $price = array_key_exists('price', $data) && $data['price'] !== null ? round((float) $data['price'], 2) : null;
        $isPublicOnline = array_key_exists('is_public_online', $data) && $data['is_public_online'] !== null ? (bool) $data['is_public_online'] : null;
        $notes = $data['notes'] ?? null;

        return DB::transaction(function () use ($availabilityStatus, $companyId, $space, $room, $bedUnit, $date, $status, $price, $isPublicOnline, $notes, $user): ?AvailabilityStatus {
            if ($this->hasActiveOccupancyBlock($companyId, $space->id, $room?->id, $bedUnit?->id, $date)) {
                throw ValidationException::withMessages([
                    'status' => 'Esta fecha ya esta bloqueada desde Ocupabilidad. Gestiona el bloqueo desde esa grilla.',
                ]);
            }

            if ($price !== null || $isPublicOnline !== null) {
                $this->upsertAvailabilityDay($companyId, $space->id, $room?->id, $bedUnit?->id, $date, $price, $isPublicOnline);
            }

            $existing = $availabilityStatus
                ? $this->lockedStatus($companyId, $availabilityStatus)
                : $this->existingStatus($companyId, $space->id, $room?->id, $bedUnit?->id, $date);

            if ($existing && $existing->source !== 'manual') {
                throw ValidationException::withMessages([
                    'status' => 'Este estado fue generado por '.$existing->source.' y no puede editarse manualmente.',
                ]);
            }

            if ($status === 'available') {
                if ($existing && $existing->source === 'manual') {
                    $existing->update(['updated_by' => $user?->id]);
                    $existing->delete();
                }

                return null;
            }

            $payload = [
                'company_id' => $companyId,
                'space_id' => $space->id,
                'space_room_id' => $room?->id,
                'room_bed_unit_id' => $bedUnit?->id,
                'date' => $date,
                'status' => $status,
                'source' => 'manual',
                'notes' => $notes,
                'updated_by' => $user?->id,
            ];

            if ($existing) {
                $existing->update($payload);

                return $existing->refresh();
            }

            $restorable = AvailabilityStatus::query()
                ->withTrashed()
                ->where('company_id', $companyId)
                ->where('space_id', $space->id)
                ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
                ->when($bedUnit, fn (Builder $query): Builder => $query->where('room_bed_unit_id', $bedUnit->id), fn (Builder $query): Builder => $query->whereNull('room_bed_unit_id'))
                ->where('date', $date)
                ->first();

            if ($restorable) {
                $restorable->restore();
                $restorable->update([
                    ...$payload,
                    'created_by' => $restorable->created_by ?: $user?->id,
                ]);

                return $restorable->refresh();
            }

            return AvailabilityStatus::query()->create([
                ...$payload,
                'created_by' => $user?->id,
            ]);
        });
    }

    public function bulkChange(int $companyId, array $data, ?User $user = null): array
    {
        [$space, $room, $bedUnit] = $this->validateResource($companyId, $data['space_id'], $data['space_room_id'] ?? null, $data['room_bed_unit_id'] ?? null);
        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->startOfDay();
        $status = $data['status'] ?? null;
        $price = array_key_exists('price', $data) && $data['price'] !== null ? round((float) $data['price'], 2) : null;
        $isPublicOnline = array_key_exists('is_public_online', $data) && $data['is_public_online'] !== null ? (bool) $data['is_public_online'] : null;
        $notes = $data['notes'] ?? null;

        if ($endDate->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'La fecha final debe ser igual o posterior a la fecha inicial.',
            ]);
        }

        if ($status === null && $price === null && $isPublicOnline === null) {
            throw ValidationException::withMessages([
                'status' => 'Selecciona un estado, ingresa un precio o cambia la reserva publica.',
            ]);
        }

        return DB::transaction(function () use ($companyId, $space, $room, $bedUnit, $startDate, $endDate, $status, $price, $isPublicOnline, $notes, $user): array {
            $dates = collect();
            $statusUpdates = 0;
            $priceUpdates = 0;
            $publicUpdates = 0;

            foreach ($startDate->daysUntil($endDate) as $date) {
                $dateString = $date->toDateString();
                $dates->push($dateString);

                if ($status !== null) {
                    $payload = [
                        'space_id' => $space->id,
                        'space_room_id' => $room?->id,
                        'room_bed_unit_id' => $bedUnit?->id,
                        'date' => $dateString,
                        'status' => $status,
                        'notes' => $notes,
                    ];

                    if ($price !== null) {
                        $payload['price'] = $price;
                    }

                    if ($isPublicOnline !== null) {
                        $payload['is_public_online'] = $isPublicOnline;
                    }

                    $this->changeStatus($companyId, $payload, null, $user);
                    $statusUpdates++;
                } elseif ($price !== null || $isPublicOnline !== null) {
                    $this->upsertAvailabilityDay($companyId, $space->id, $room?->id, $bedUnit?->id, $dateString, $price, $isPublicOnline);
                }

                if ($price !== null) {
                    $priceUpdates++;
                }

                if ($isPublicOnline !== null) {
                    $publicUpdates++;
                }
            }

            return [
                'dates' => $dates->all(),
                'total_dates' => $dates->count(),
                'status_updated' => $statusUpdates,
                'price_updated' => $priceUpdates,
                'public_updated' => $publicUpdates,
            ];
        });
    }

    private function upsertAvailabilityDay(int $companyId, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date, ?float $price, ?bool $isPublicOnline = null): AvailabilityDay
    {
        $existing = AvailabilityDay::query()
            ->where('company_id', $companyId)
            ->where('space_id', $spaceId)
            ->when($roomId, fn (Builder $query): Builder => $query->where('space_room_id', $roomId), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->when($bedUnitId, fn (Builder $query): Builder => $query->where('room_bed_unit_id', $bedUnitId), fn (Builder $query): Builder => $query->whereNull('room_bed_unit_id'))
            ->where('date', $date)
            ->first();

        $payload = [
            'company_id' => $companyId,
            'space_id' => $spaceId,
            'space_room_id' => $roomId,
            'room_bed_unit_id' => $bedUnitId,
            'date' => $date,
            'price' => $price ?? $existing?->price,
            'status' => 'available',
            'is_public_online' => $isPublicOnline ?? $existing?->is_public_online ?? true,
        ];

        if ($existing) {
            $existing->update($payload);

            return $existing->refresh();
        }

        return AvailabilityDay::query()->create($payload);
    }

    private function existingStatus(int $companyId, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?AvailabilityStatus
    {
        return AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->where('space_id', $spaceId)
            ->when($roomId, fn (Builder $query): Builder => $query->where('space_room_id', $roomId), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->when($bedUnitId, fn (Builder $query): Builder => $query->where('room_bed_unit_id', $bedUnitId), fn (Builder $query): Builder => $query->whereNull('room_bed_unit_id'))
            ->where('date', $date)
            ->first();
    }

    private function lockedStatus(int $companyId, AvailabilityStatus $availabilityStatus): AvailabilityStatus
    {
        if ((int) $availabilityStatus->company_id !== $companyId) {
            abort(404);
        }

        return $availabilityStatus;
    }

    private function validateResource(int $companyId, int|string $spaceId, int|string|null $roomId, int|string|null $bedUnitId): array
    {
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($spaceId)
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                'space_id' => 'El espacio debe pertenecer a tu empresa y estar activo.',
            ]);
        }

        $isShared = $space->spaceMode?->slug === 'compartido';

        if (! $isShared && filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Un espacio privado no permite seleccionar habitacion.',
            ]);
        }

        if (! $isShared && filled($bedUnitId)) {
            throw ValidationException::withMessages([
                'room_bed_unit_id' => 'Un espacio privado no permite seleccionar cama.',
            ]);
        }

        if (! $isShared) {
            return [$space, null, null];
        }

        if (! filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Selecciona una habitacion activa para el alojamiento compartido.',
            ]);
        }

        $room = SpaceRoom::query()
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereKey($roomId)
            ->first();

        if (! $room) {
            throw ValidationException::withMessages([
                'space_room_id' => 'La habitacion seleccionada no pertenece al alojamiento.',
            ]);
        }

        if (! filled($bedUnitId)) {
            return [$space, $room, null];
        }

        $bedUnit = RoomBedUnit::query()
            ->where('company_id', $companyId)
            ->where('space_room_id', $room->id)
            ->where('status', 'active')
            ->whereKey($bedUnitId)
            ->first();

        if (! $bedUnit) {
            throw ValidationException::withMessages([
                'room_bed_unit_id' => 'La cama seleccionada no pertenece a la habitacion.',
            ]);
        }

        return [$space, $room, $bedUnit];
    }

    private function hasActiveOccupancyBlock(int $companyId, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): bool
    {
        $query = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date);

        if ($bedUnitId) {
            return (clone $query)->where('room_bed_unit_id', $bedUnitId)->exists()
                || (clone $query)->where('space_room_id', $roomId)->whereNull('room_bed_unit_id')->exists();
        }

        return $query
            ->when(
                $roomId,
                fn (Builder $query): Builder => $query->where('space_room_id', $roomId),
                fn (Builder $query): Builder => $query->where('space_id', $spaceId)->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->exists();
    }

    private function statusesForRange(int $companyId, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $startDate, string $lastNight): Collection
    {
        return AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $lastNight])
            ->where(function (Builder $query) use ($space, $room, $bedUnit): void {
                if ($bedUnit) {
                    $query
                        ->where('room_bed_unit_id', $bedUnit->id)
                        ->orWhere(fn (Builder $query): Builder => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'))
                        ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'));

                    return;
                }

                if ($room) {
                    $query
                        ->where('space_room_id', $room->id)
                        ->orWhere(fn (Builder $query): Builder => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'));

                    return;
                }

                $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id');
            })
            ->get();
    }

    private function hasActiveOccupancyBlockInRange(int $companyId, int $spaceId, ?int $roomId, ?int $bedUnitId, string $startDate, string $lastNight, int|string|null $reservationGroupId = null): bool
    {
        $query = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $startDate)
            ->when($reservationGroupId, fn (Builder $query): Builder => $this->excludeReservationGroupBlocks($query, (int) $reservationGroupId));

        if ($bedUnitId) {
            return (clone $query)->where('room_bed_unit_id', $bedUnitId)->exists()
                || (clone $query)->where('space_room_id', $roomId)->whereNull('room_bed_unit_id')->exists()
                || (clone $query)->where('space_id', $spaceId)->whereNull('space_room_id')->exists();
        }

        return $query
            ->when(
                $roomId,
                fn (Builder $query): Builder => $query->where('space_room_id', $roomId),
                fn (Builder $query): Builder => $query->where('space_id', $spaceId)->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->exists();
    }

    private function excludeReservationGroupBlocks(Builder $query, int $reservationGroupId): Builder
    {
        return $query
            ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId))
            ->whereDoesntHave('reservationRoom.reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId))
            ->whereDoesntHave('reservationBedUnit.reservation', fn (Builder $reservation): Builder => $reservation->where('reservation_group_id', $reservationGroupId));
    }

    private function upsertOccupiedStatus(int $companyId, Stay $stay, string $date, ?User $user): void
    {
        $payload = [
            'company_id' => $companyId,
            'space_id' => $stay->space_id,
            'space_room_id' => $stay->space_room_id,
            'room_bed_unit_id' => $stay->room_bed_unit_id,
            'date' => $date,
            'status' => 'occupied',
            'source' => 'check_in',
            'notes' => 'Check-in '.$stay->checkInGroup?->code,
            'updated_by' => $user?->id,
        ];

        $existing = $this->existingStatus($companyId, $stay->space_id, $stay->space_room_id, $stay->room_bed_unit_id, $date);

        if ($existing) {
            $existing->update($payload);

            return;
        }

        AvailabilityStatus::query()->create([
            ...$payload,
            'created_by' => $user?->id,
        ]);
    }
}
