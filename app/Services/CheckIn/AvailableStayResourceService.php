<?php

namespace App\Services\CheckIn;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class AvailableStayResourceService
{
    public function availableForMove(Stay $stay, CarbonImmutable $startDate, CarbonImmutable $checkOutDate): array
    {
        return collect($this->resourcesForDates((int) $stay->company_id, $startDate, $checkOutDate))
            ->reject(fn (array $resource): bool => $resource['disabled'] || $resource['key'] === $this->resourceKey($stay))
            ->values()
            ->all();
    }

    public function resourceKey(Stay $stay): string
    {
        if ($stay->room_bed_unit_id) {
            return 'bed:'.$stay->room_bed_unit_id;
        }

        if ($stay->space_room_id) {
            return 'room:'.$stay->space_room_id;
        }

        return 'space:'.$stay->space_id;
    }

    private function resourcesForDates(int $companyId, CarbonImmutable $checkInDate, CarbonImmutable $checkOutDate): array
    {
        $lastNight = $checkOutDate->subDay();
        $availabilityStatuses = AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$checkInDate->toDateString(), $lastNight->toDateString()])
            ->get();
        $blocks = OccupancyBlock::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $lastNight->toDateString())
            ->whereDate('end_date', '>=', $checkInDate->toDateString())
            ->get();

        return Space::query()
            ->with([
                'spaceMode',
                'rooms' => fn ($query) => $query
                    ->with(['beds', 'bedUnits.bedType'])
                    ->where('status', 'active')
                    ->orderBy('name'),
            ])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('spaceMode', fn (Builder $query): Builder => $query->whereIn('slug', ['privado', 'compartido']))
            ->orderByRaw('coalesce(title, name) asc')
            ->get()
            ->flatMap(function (Space $space) use ($availabilityStatuses, $blocks): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    return $space->rooms
                        ->flatMap(function (SpaceRoom $room) use ($space, $availabilityStatuses, $blocks): array {
                            $resources = [];

                            if (in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                                $resources[] = $this->resourcePayload($space, $room, null, $availabilityStatuses, $blocks);
                            }

                            if (in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                                foreach ($room->bedUnits->where('status', 'active') as $bedUnit) {
                                    $resources[] = $this->resourcePayload($space, $room, $bedUnit, $availabilityStatuses, $blocks);
                                }
                            }

                            return $resources;
                        })
                        ->all();
                }

                return [$this->resourcePayload($space, null, null, $availabilityStatuses, $blocks)];
            })
            ->values()
            ->all();
    }

    private function resourcePayload(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, $availabilityStatuses, $blocks): array
    {
        $status = $this->resourceStatus($availabilityStatuses, $blocks, $space, $room, $bedUnit);
        $spaceLabel = $space->name ?: $space->title ?: 'Alojamiento';
        $roomLabel = $room ? ($room->name ?: $room->title ?: 'Habitacion') : null;
        $fullRoomStatus = $room ? $this->fullRoomStatus($availabilityStatuses, $blocks, $space, $room) : null;
        $status = $room && ! $bedUnit && $room->sale_mode === 'flexible' ? $fullRoomStatus : $status;
        $label = match (true) {
            $bedUnit !== null => $spaceLabel.' / '.$roomLabel.' / '.$bedUnit->label,
            $room !== null => $spaceLabel.' / '.$roomLabel,
            default => $space->title ?: $space->name ?: 'Alojamiento',
        };

        return [
            'key' => $bedUnit ? 'bed:'.$bedUnit->id : (($room ? 'room:' : 'space:').($room?->id ?? $space->id)),
            'label' => $label,
            'resource_type' => $bedUnit ? 'shared_bed_unit' : ($room ? 'shared_room' : 'private_space'),
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'capacity' => $bedUnit ? 1 : $this->resourceCapacity($space, $room),
            'status' => $status,
            'disabled' => $status !== 'available',
            'sale_mode' => $room?->sale_mode,
        ];
    }

    private function resourceStatus($availabilityStatuses, $blocks, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): string
    {
        if ($this->hasBlockingBlock($blocks, $space, $room, $bedUnit, false)) {
            return 'closed';
        }

        $statuses = $availabilityStatuses
            ->filter(fn (AvailabilityStatus $status): bool => match (true) {
                $bedUnit !== null => (
                    (int) ($status->room_bed_unit_id ?? 0) === (int) $bedUnit->id
                    || ((int) ($status->space_room_id ?? 0) === (int) $room->id && $status->room_bed_unit_id === null)
                    || ((int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null)
                ),
                $room !== null => (
                    (int) ($status->space_room_id ?? 0) === (int) $room->id
                    || ((int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null)
                ),
                default => (int) $status->space_id === (int) $space->id && $status->space_room_id === null && $status->room_bed_unit_id === null,
            })
            ->pluck('status');

        foreach (['occupied', 'closed', 'reserved'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'available';
    }

    private function fullRoomStatus($availabilityStatuses, $blocks, Space $space, SpaceRoom $room): string
    {
        if ($this->hasBlockingBlock($blocks, $space, $room, null, true)) {
            return 'closed';
        }

        $statuses = $availabilityStatuses
            ->filter(fn (AvailabilityStatus $status): bool => (int) $status->space_id === (int) $space->id
                && (((int) ($status->space_room_id ?? 0) === (int) $room->id)
                    || ($status->space_room_id === null && $status->room_bed_unit_id === null)))
            ->pluck('status');

        foreach (['occupied', 'closed', 'reserved'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return 'available';
    }

    private function hasBlockingBlock($blocks, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, bool $includeBedUnits): bool
    {
        return $blocks->contains(function (OccupancyBlock $block) use ($space, $room, $bedUnit, $includeBedUnits): bool {
            if ((int) $block->space_id !== (int) $space->id) {
                return false;
            }

            if (! $room) {
                return $block->space_room_id === null && $block->room_bed_unit_id === null;
            }

            if ($bedUnit) {
                return (int) ($block->room_bed_unit_id ?? 0) === (int) $bedUnit->id
                    || ((int) ($block->space_room_id ?? 0) === (int) $room->id && $block->room_bed_unit_id === null)
                    || ($block->space_room_id === null && $block->room_bed_unit_id === null);
            }

            return ($block->space_room_id === null && $block->room_bed_unit_id === null)
                || ((int) ($block->space_room_id ?? 0) === (int) $room->id
                    && ($includeBedUnits || $block->room_bed_unit_id === null));
        });
    }

    private function resourceCapacity(Space $space, ?SpaceRoom $room): int
    {
        if (! $room) {
            return max((int) ($space->max_capacity ?: 1), 1);
        }

        return max(
            (int) ($room->max_capacity ?: 0)
                ?: (int) $room->beds->sum('total_capacity')
                ?: (int) $room->bedUnits->where('status', 'active')->count(),
            1,
        );
    }
}
