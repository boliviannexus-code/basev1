<?php

namespace App\Services\Occupancy;

use App\Models\AvailabilityDay;
use App\Models\OccupancyBlock;
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

        $this->ensureNoOverlap(
            companyId: $companyId,
            space: $space,
            room: $room,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            ignoreBlock: $ignoreBlock,
        );

        $this->ensureAvailabilityIsOpen(
            companyId: $companyId,
            space: $space,
            room: $room,
            startDate: $data['start_date'],
            endDate: $data['end_date'],
        );

        return [$space, $room];
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

    private function ensureNoOverlap(
        int $companyId,
        Space $space,
        ?SpaceRoom $room,
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

        if ($room) {
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

    private function ensureAvailabilityIsOpen(
        int $companyId,
        Space $space,
        ?SpaceRoom $room,
        string $startDate,
        string $endDate,
    ): void {
        $availabilityDays = AvailabilityDay::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$startDate, $endDate])
            ->when(
                $room,
                fn ($query) => $query->where('space_room_id', $room->id),
                fn ($query) => $query->where('space_id', $space->id)->whereNull('space_room_id'),
            )
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $day->date->toDateString());

        $hasClosedDate = collect(Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate)->addDay()))
            ->contains(function (Carbon $date) use ($availabilityDays): bool {
                $availabilityDay = $availabilityDays->get($date->toDateString());

                return ! $availabilityDay
                    || $availabilityDay->price === null
                    || $availabilityDay->status === 'closed';
            });

        if ($hasClosedDate) {
            throw ValidationException::withMessages([
                'start_date' => 'No se puede operar sobre fechas sin precio o cerradas desde Disponibilidad.',
            ]);
        }
    }
}
