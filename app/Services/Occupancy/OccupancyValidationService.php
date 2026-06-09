<?php

namespace App\Services\Occupancy;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OccupancyValidationService
{
    public function validatePayload(array $data, int $companyId, ?OccupancyBlock $ignoreBlock = null): array
    {
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($data['space_id'])
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                'space_id' => 'El alojamiento debe pertenecer a tu empresa y estar activo.',
            ]);
        }

        $room = $this->resolveRoom($data, $space, $companyId);
        $bedUnit = $this->resolveBedUnit($data, $room, $companyId);

        $this->ensureNoOverlap(
            companyId: $companyId,
            space: $space,
            room: $room,
            bedUnit: $bedUnit,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            ignoreBlock: $ignoreBlock,
        );

        $this->ensureAvailabilityAllowsOccupancy(
            companyId: $companyId,
            space: $space,
            room: $room,
            bedUnit: $bedUnit,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
        );

        return [$space, $room, $bedUnit];
    }

    private function resolveRoom(array $data, Space $space, int $companyId): ?SpaceRoom
    {
        $isShared = $space->spaceMode?->slug === 'compartido';
        $roomId = $data['space_room_id'] ?? null;

        if (! $isShared && filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Un alojamiento privado no permite seleccionar habitacion.',
            ]);
        }

        if (! $isShared) {
            return null;
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

        return $room;
    }

    private function resolveBedUnit(array $data, ?SpaceRoom $room, int $companyId): ?RoomBedUnit
    {
        $bedUnitId = $data['room_bed_unit_id'] ?? null;

        if (! filled($bedUnitId)) {
            return null;
        }

        if (! $room) {
            throw ValidationException::withMessages([
                'room_bed_unit_id' => 'Selecciona una habitacion antes de seleccionar cama.',
            ]);
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

        return $bedUnit;
    }

    private function ensureNoOverlap(
        int $companyId,
        Space $space,
        ?SpaceRoom $room,
        ?RoomBedUnit $bedUnit,
        string $startDate,
        string $endDate,
        ?OccupancyBlock $ignoreBlock,
    ): void {
        $query = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($ignoreBlock) {
            $query->whereKeyNot($ignoreBlock->id);
        }

        if ($bedUnit) {
            $query->where(function ($query) use ($bedUnit, $room): void {
                $query
                    ->where('room_bed_unit_id', $bedUnit->id)
                    ->orWhere(fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'));
            });
        } elseif ($room) {
            $query->where('space_room_id', $room->id);
        } else {
            $query->where('space_id', $space->id)->whereNull('space_room_id');
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'start_date' => 'Ya existe un bloqueo activo en ese rango de fechas.',
            ]);
        }

    }

    private function ensureAvailabilityAllowsOccupancy(
        int $companyId,
        Space $space,
        ?SpaceRoom $room,
        ?RoomBedUnit $bedUnit,
        string $startDate,
        string $endDate,
    ): void {
        $availabilityStatuses = AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->when(
                $bedUnit,
                fn ($query) => $query->where(fn ($query) => $query
                    ->where('room_bed_unit_id', $bedUnit->id)
                    ->orWhere(fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'))),
                fn ($query) => $query->when(
                    $room,
                    fn ($query) => $query->where('space_room_id', $room->id),
                    fn ($query) => $query->where('space_id', $space->id)->whereNull('space_room_id'),
                ),
            )
            ->get()
            ->keyBy(fn (AvailabilityStatus $status): string => $status->date->toDateString());

        $hasBlockedDate = collect(Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate)->addDay()))
            ->contains(function (Carbon $date) use ($availabilityStatuses): bool {
                $availabilityStatus = $availabilityStatuses->get($date->toDateString());

                return in_array($availabilityStatus?->status, ['closed', 'reserved', 'occupied'], true);
            });

        if ($hasBlockedDate) {
            throw ValidationException::withMessages([
                'start_date' => 'No se puede operar sobre fechas cerradas, reservadas u ocupadas desde Disponibilidad.',
            ]);
        }
    }
}
