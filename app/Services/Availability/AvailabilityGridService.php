<?php

namespace App\Services\Availability;

use App\Models\AvailabilityDay;
use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AvailabilityGridService
{
    public const STATUS_META = [
        'available' => ['label' => 'Disponible', 'short_label' => 'Disp.', 'tone' => 'available'],
        'closed' => ['label' => 'Cerrado', 'short_label' => 'Cerr.', 'tone' => 'closed'],
        'reserved' => ['label' => 'Reservado', 'short_label' => 'Res.', 'tone' => 'reserved'],
        'occupied' => ['label' => 'Ocupado', 'short_label' => 'Ocup.', 'tone' => 'occupied'],
    ];

    public function gridData(int $companyId, array $filters): array
    {
        $weekStart = $this->weekStart($filters['week_start'] ?? $filters['start_date'] ?? null);
        $weekEnd = $weekStart->copy()->addDays(6);
        $dates = $this->dates($weekStart);
        $spaces = $this->spaces($companyId, $filters);
        $statuses = $this->statuses($companyId, $weekStart, $weekEnd, $filters);
        $prices = $this->prices($companyId, $weekStart, $weekEnd, $filters);
        $blocks = $this->blocks($companyId, $weekStart, $weekEnd, $filters);
        $rows = $this->rows($spaces, $dates, $statuses, $prices, $blocks, $filters);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'start_date' => $weekStart->toDateString(),
            'end_date' => $weekEnd->toDateString(),
            'previous_period' => $this->previousPeriod($weekStart)->toDateString(),
            'current_period' => now()->startOfDay()->toDateString(),
            'next_period' => $weekStart->copy()->addWeek()->toDateString(),
            'can_go_previous' => $weekStart->greaterThan(now()->startOfDay()),
            'dates' => $dates,
            'rows' => $rows,
            'summary' => $this->summary($rows),
        ];
    }

    public function spacesForFilters(int $companyId): Collection
    {
        return Space::query()
            ->with([
                'spaceMode',
                'rooms' => fn ($query) => $query
                    ->with(['beds.bedType', 'bedUnits.bedType'])
                    ->where('status', 'active')
                    ->orderBy('sort_order')
                    ->orderBy('name'),
            ])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('spaceMode', fn (Builder $query): Builder => $query->whereIn('slug', ['privado', 'compartido']))
            ->orderByRaw('coalesce(title, name) asc')
            ->get();
    }

    private function weekStart(?string $date): Carbon
    {
        $today = now()->startOfDay();
        $requested = filled($date)
            ? Carbon::parse($date)->startOfDay()
            : $today->copy();

        return $requested->lessThan($today) ? $today : $requested;
    }

    private function previousPeriod(Carbon $weekStart): Carbon
    {
        $today = now()->startOfDay();
        $previous = $weekStart->copy()->subWeek();

        return $previous->lessThan($today) ? $today : $previous;
    }

    private function dates(Carbon $weekStart): array
    {
        $dayNames = ['Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab', 'Dom'];

        return collect(range(0, 6))
            ->map(function (int $offset) use ($weekStart, $dayNames): array {
                $date = $weekStart->copy()->addDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => $dayNames[$date->dayOfWeekIso - 1].' '.$date->format('d'),
                    'iso_day' => $date->dayOfWeekIso,
                    'is_today' => $date->isToday(),
                    'is_past' => $date->lessThan(now()->startOfDay()),
                ];
            })
            ->all();
    }

    private function spaces(int $companyId, array $filters): Collection
    {
        return $this->spacesForFilters($companyId)
            ->filter(function (Space $space) use ($filters): bool {
                if (filled($filters['space_id'] ?? null) && (int) $filters['space_id'] !== (int) $space->id) {
                    return false;
                }

                $mode = $space->spaceMode?->slug;

                return match ($filters['type'] ?? 'all') {
                    'private' => $mode === 'privado',
                    'shared' => $mode === 'compartido',
                    default => true,
                };
            })
            ->values();
    }

    private function statuses(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get()
            ->keyBy(fn (AvailabilityStatus $status): string => $this->resourceKey(
                (int) $status->space_id,
                $status->space_room_id ? (int) $status->space_room_id : null,
                $status->date->toDateString(),
                $status->room_bed_unit_id ? (int) $status->room_bed_unit_id : null,
            ));
    }

    private function prices(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return AvailabilityDay::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $this->resourceKey(
                (int) $day->space_id,
                $day->space_room_id ? (int) $day->space_room_id : null,
                $day->date->toDateString(),
                $day->room_bed_unit_id ? (int) $day->room_bed_unit_id : null,
            ));
    }

    private function blocks(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return OccupancyBlock::query()
            ->with(['reservation', 'reservationRoom.reservation', 'reservationBedUnit.reservation'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->when(filled($filters['space_id'] ?? null), fn (Builder $query): Builder => $query->where('space_id', $filters['space_id']))
            ->get();
    }

    private function rows(Collection $spaces, array $dates, Collection $statuses, Collection $prices, Collection $blocks, array $filters): array
    {
        return $spaces
            ->flatMap(function (Space $space) use ($dates, $statuses, $prices, $blocks): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    $roomRows = $space->rooms
                        ->flatMap(fn (SpaceRoom $room): array => $this->sharedRoomRows($room, $space, $dates, $statuses, $prices, $blocks))
                        ->values()
                        ->all();

                    return [
                        [
                            'type' => 'shared_space_group',
                            'space_id' => $space->id,
                            'room_id' => null,
                            'label' => $this->spaceLabel($space),
                            'cells' => [],
                        ],
                        ...$roomRows,
                    ];
                }

                return [[
                    'type' => 'private_space',
                    'space_id' => $space->id,
                    'room_id' => null,
                    'room_bed_unit_id' => null,
                    'label' => $this->spaceLabel($space),
                    'cells' => $this->cells($dates, $statuses, $prices, $blocks, $space->id, null, null),
                ]];
            })
            ->filter(fn (array $row): bool => $this->rowMatchesStatus($row, $filters['status'] ?? null))
            ->values()
            ->all();
    }

    private function sharedRoomRows(SpaceRoom $room, Space $space, array $dates, Collection $statuses, Collection $prices, Collection $blocks): array
    {
        $roomRow = [
            'type' => 'shared_room',
            'space_id' => $space->id,
            'room_id' => $room->id,
            'room_bed_unit_id' => null,
            'label' => $this->roomLabel($room),
            'space_label' => $this->spaceLabel($space),
            'sale_mode' => $room->sale_mode ?? 'full_room',
            'cells' => $this->cells($dates, $statuses, $prices, $blocks, $space->id, $room->id, null),
        ];

        if (! in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
            return [$roomRow];
        }

        $roomRow['cells'] = [];
        $roomRow['is_group_header'] = true;

        $bedRows = $room->bedUnits
            ->where('status', 'active')
            ->map(fn (RoomBedUnit $unit): array => [
                'type' => 'shared_bed_unit',
                'space_id' => $space->id,
                'room_id' => $room->id,
                'room_bed_unit_id' => $unit->id,
                'label' => $unit->label.' - '.($unit->bedType?->name ?: 'Cama'),
                'space_label' => $this->spaceLabel($space),
                'room_label' => $this->roomLabel($room),
                'sale_mode' => $room->sale_mode,
                'cells' => $this->cells($dates, $statuses, $prices, $blocks, $space->id, $room->id, $unit->id),
            ])
            ->values()
            ->all();

        return [$roomRow, ...$bedRows];
    }

    private function cells(array $dates, Collection $statuses, Collection $prices, Collection $blocks, int $spaceId, ?int $roomId, ?int $bedUnitId): array
    {
        return collect($dates)
            ->map(function (array $date) use ($statuses, $prices, $blocks, $spaceId, $roomId, $bedUnitId): array {
                $availabilityStatus = $this->availabilityStatusForDate($statuses, $spaceId, $roomId, $bedUnitId, $date['date']);
                $availabilityDay = $this->availabilityDayForDate($prices, $spaceId, $roomId, $bedUnitId, $date['date']);
                $block = $this->blockForDate($blocks, $spaceId, $roomId, $bedUnitId, $date['date']);
                $hasPartialBedBlock = $bedUnitId === null && $roomId !== null && ! $block && $this->hasBedUnitBlockForDate($blocks, $spaceId, $roomId, $date['date']);
                $status = $hasPartialBedBlock ? 'reserved' : ($block ? 'closed' : ($availabilityStatus?->status ?? 'available'));
                $meta = self::STATUS_META[$status];
                $isPast = Carbon::parse($date['date'])->startOfDay()->lessThan(now()->startOfDay());
                $source = ($block || $hasPartialBedBlock) ? 'ocupabilidad' : $availabilityStatus?->source;
                $guestName = $this->guestName($block);

                return [
                    'date' => $date['date'],
                    'status' => $status,
                    'label' => $guestName ?: ($hasPartialBedBlock ? 'Parcial' : $meta['label']),
                    'short_label' => $meta['short_label'],
                    'tone' => $meta['tone'],
                    'source' => $source,
                    'notes' => $block?->title ?? $availabilityStatus?->notes,
                    'price' => $availabilityDay?->price !== null ? (float) $availabilityDay->price : null,
                    'price_display' => $availabilityDay?->price !== null ? number_format((float) $availabilityDay->price, 2, '.', '') : null,
                    'is_public_online' => $availabilityDay?->is_public_online ?? true,
                    'public_online_label' => ($availabilityDay?->is_public_online ?? true) ? 'Publico en linea' : 'Fuera de linea publico',
                    'availability_day_id' => $availabilityDay?->id,
                    'availability_status_id' => $availabilityStatus?->id,
                    'occupancy_block_id' => $block?->id,
                    'room_bed_unit_id' => $bedUnitId,
                    'guest_name' => $guestName,
                    'is_past' => $isPast,
                    'actions' => $isPast || $block || $hasPartialBedBlock || ($availabilityStatus?->source && $availabilityStatus->source !== 'manual')
                        ? []
                        : ['change_status', 'add_note'],
                ];
            })
            ->all();
    }

    private function blockForDate(Collection $blocks, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?OccupancyBlock
    {
        $matchingBlocks = $blocks->filter(fn (OccupancyBlock $block): bool => (int) $block->space_id === $spaceId
            && $block->start_date->toDateString() <= $date
            && $block->end_date->toDateString() >= $date);

        if ($bedUnitId !== null) {
            return $matchingBlocks->first(fn (OccupancyBlock $block): bool => (int) ($block->room_bed_unit_id ?? 0) === $bedUnitId)
                ?: $matchingBlocks->first(fn (OccupancyBlock $block): bool => (int) ($block->space_room_id ?? 0) === (int) $roomId && $block->room_bed_unit_id === null);
        }

        return $matchingBlocks->first(fn (OccupancyBlock $block): bool => (int) ($block->space_room_id ?? 0) === (int) ($roomId ?? 0) && $block->room_bed_unit_id === null);
    }

    private function availabilityStatusForDate(Collection $statuses, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?AvailabilityStatus
    {
        if ($bedUnitId !== null) {
            return $statuses->get($this->resourceKey($spaceId, $roomId, $date, $bedUnitId))
                ?: $statuses->get($this->resourceKey($spaceId, $roomId, $date));
        }

        return $statuses->get($this->resourceKey($spaceId, $roomId, $date));
    }

    private function availabilityDayForDate(Collection $prices, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?AvailabilityDay
    {
        if ($bedUnitId !== null) {
            return $prices->get($this->resourceKey($spaceId, $roomId, $date, $bedUnitId))
                ?: $prices->get($this->resourceKey($spaceId, $roomId, $date));
        }

        return $prices->get($this->resourceKey($spaceId, $roomId, $date));
    }

    private function hasBedUnitBlockForDate(Collection $blocks, int $spaceId, int $roomId, string $date): bool
    {
        return $blocks->contains(fn (OccupancyBlock $block): bool => (int) $block->space_id === $spaceId
            && (int) ($block->space_room_id ?? 0) === $roomId
            && $block->room_bed_unit_id !== null
            && $block->start_date->toDateString() <= $date
            && $block->end_date->toDateString() >= $date);
    }

    private function rowMatchesStatus(array $row, ?string $status): bool
    {
        if (! filled($status) || $row['type'] === 'shared_space_group') {
            return true;
        }

        return collect($row['cells'])->contains(fn (array $cell): bool => $cell['status'] === $status);
    }

    private function summary(array $rows): array
    {
        $cells = collect($rows)
            ->reject(fn (array $row): bool => $row['type'] === 'shared_space_group')
            ->flatMap(fn (array $row): array => $row['cells']);

        return [
            'resources' => collect($rows)->where('type', '!=', 'shared_space_group')->count(),
            'available' => $cells->where('status', 'available')->count(),
            'closed' => $cells->where('status', 'closed')->count(),
            'reserved' => $cells->where('status', 'reserved')->count(),
            'occupied' => $cells->where('status', 'occupied')->count(),
        ];
    }

    private function resourceKey(int $spaceId, ?int $roomId, string $date, ?int $bedUnitId = null): string
    {
        return $spaceId.'|'.($roomId ?: 'space').'|'.($bedUnitId ? 'bed:'.$bedUnitId : 'room').'|'.$date;
    }

    private function guestName(?OccupancyBlock $block): ?string
    {
        return $block?->reservation?->guest_name
            ?: $block?->reservationRoom?->reservation?->guest_name
            ?: $block?->reservationBedUnit?->guest_name
            ?: $block?->reservationBedUnit?->reservation?->guest_name;
    }

    private function spaceLabel(Space $space): string
    {
        if ($space->spaceMode?->slug === 'compartido') {
            return $space->name ?: $space->title ?: 'Alojamiento compartido';
        }

        return trim(($space->title ?: $space->name ?: 'Espacio privado').(((int) $space->max_capacity > 0) ? ' - Cap. '.$space->max_capacity : ''));
    }

    private function roomLabel(SpaceRoom $room): string
    {
        return collect([
            $room->name ?: $room->title ?: 'Habitacion',
            filled($room->room_number) && trim((string) $room->room_number) !== trim((string) ($room->name ?: $room->title ?: 'Habitacion')) ? 'Hab. '.$room->room_number : null,
            $room->relationLoaded('beds') ? $room->beds->map(fn ($bed) => trim($bed->quantity.' '.($bed->bedType?->name ?: 'cama')))->filter()->implode(', ') : null,
        ])->filter()->implode(' - ');
    }
}
