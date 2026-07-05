<?php

namespace App\Services\Occupancy;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OccupancyGridService
{
    public const STATUS_META = [
        'available' => ['label' => 'Libre', 'tone' => 'available'],
        'manual_block' => ['label' => 'Bloqueado', 'tone' => 'blocked'],
        'maintenance' => ['label' => 'Mant.', 'tone' => 'blocked'],
        'owner_use' => ['label' => 'Prop.', 'tone' => 'blocked'],
        'unavailable' => ['label' => 'No disp.', 'tone' => 'blocked'],
        'closed' => ['label' => 'Cerrado', 'tone' => 'blocked'],
        'reserved' => ['label' => 'Reservado', 'tone' => 'reserved-unpaid'],
        'occupied' => ['label' => 'Ocupado', 'tone' => 'occupied-paid'],
        'checked_out' => ['label' => 'Historial', 'tone' => 'checkout-clean'],
    ];

    public function weekData(int $companyId, array $filters): array
    {
        $weekStart = $this->weekStart($filters['week_start'] ?? null);
        $weekEnd = $weekStart->copy()->addDays(6);
        $dates = $this->dates($weekStart);
        $spaces = $this->spaces($companyId, $filters);
        $blocks = $this->blocks($companyId, $weekStart, $weekEnd, $filters);
        $availabilityStatuses = $this->availabilityStatuses($companyId, $weekStart, $weekEnd, $filters);
        $stays = $this->stays($companyId, $weekStart, $weekEnd, $filters);

        return [
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'previous_week' => $weekStart->copy()->subDay()->toDateString(),
            'current_week' => now()->subDay()->startOfDay()->toDateString(),
            'next_week' => $weekStart->copy()->addDay()->toDateString(),
            'dates' => $dates,
            'rows' => $this->rows($spaces, $dates, $blocks, $availabilityStatuses, $stays, $filters),
            'summary' => $this->summary($spaces, $blocks),
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
        return filled($date)
            ? Carbon::parse($date)->startOfDay()
            : now()->subDay()->startOfDay();
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
                ];
            })
            ->all();
    }

    private function spaces(int $companyId, array $filters): Collection
    {
        $view = $filters['view'] ?? 'private';

        return $this->spacesForFilters($companyId)
            ->filter(function (Space $space) use ($view): bool {
                $mode = $space->spaceMode?->slug;

                if ($view === 'private') {
                    return $mode === 'privado';
                }

                if (str_starts_with((string) $view, 'shared:')) {
                    return $mode === 'compartido' && (int) Str::after((string) $view, 'shared:') === (int) $space->id;
                }

                return $mode === 'privado';
            })
            ->values();
    }

    private function blocks(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return OccupancyBlock::query()
            ->with(['space.spaceMode', 'room', 'bedUnit', 'reservation.reservationGroup', 'reservationRoom.reservation.reservationGroup', 'reservationBedUnit.reservation.reservationGroup'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $weekEnd->toDateString())
            ->where('end_date', '>=', $weekStart->toDateString())
            ->where(fn (Builder $query): Builder => $this->applyViewFilter($query, $filters))
            ->get();
    }

    private function availabilityStatuses(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->where(fn (Builder $query): Builder => $this->applyViewFilter($query, $filters))
            ->get()
            ->keyBy(fn (AvailabilityStatus $status): string => $this->availabilityKey(
                (int) $status->space_id,
                $status->space_room_id ? (int) $status->space_room_id : null,
                $status->date->toDateString(),
                $status->room_bed_unit_id ? (int) $status->room_bed_unit_id : null,
            ));
    }

    private function stays(int $companyId, Carbon $weekStart, Carbon $weekEnd, array $filters): Collection
    {
        return Stay::query()
            ->with(['holderGuest', 'checkInGroup', 'accountStatement'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->whereDate('check_in_date', '<=', $weekEnd->toDateString())
            ->whereDate('check_out_date', '>', $weekStart->toDateString())
            ->where(fn (Builder $query): Builder => $this->applyViewFilter($query, $filters))
            ->get();
    }

    private function rows(Collection $spaces, array $dates, Collection $blocks, Collection $availabilityStatuses, Collection $stays, array $filters): array
    {
        return $spaces
            ->flatMap(function (Space $space) use ($dates, $blocks, $availabilityStatuses, $stays, $filters): array {
                if ($space->spaceMode?->slug === 'compartido') {
                    $roomRows = $space->rooms
                        ->flatMap(fn (SpaceRoom $room): array => $this->sharedRoomRows($room, $space, $dates, $blocks, $availabilityStatuses, $stays, $filters))
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
                    'cells' => $this->cells($dates, $blocks, $availabilityStatuses, $stays, $space->id, null, null, $filters),
                ]];
            })
            ->values()
            ->all();
    }

    private function sharedRoomRows(
        SpaceRoom $room,
        Space $space,
        array $dates,
        Collection $blocks,
        Collection $availabilityStatuses,
        Collection $stays,
        array $filters,
    ): array {
        $usesIndividualBedRows = in_array($room->sale_mode, ['bed_unit', 'flexible'], true);
        $roomRow = [
            'type' => 'shared_room',
            'space_id' => $space->id,
            'room_id' => $room->id,
            'room_bed_unit_id' => null,
            'label' => $usesIndividualBedRows ? $this->roomName($room) : $this->roomLabel($room),
            'space_label' => $this->spaceLabel($space),
            'sale_mode' => $room->sale_mode ?? 'full_room',
            'cells' => $this->cells($dates, $blocks, $availabilityStatuses, $stays, $space->id, $room->id, null, $filters),
        ];

        if (! $usesIndividualBedRows) {
            return [$roomRow];
        }

        $bedRows = $room->bedUnits
            ->where('status', 'active')
            ->values()
            ->map(fn (RoomBedUnit $unit, int $index): array => [
                'type' => 'shared_bed_unit',
                'space_id' => $space->id,
                'room_id' => $room->id,
                'room_bed_unit_id' => $unit->id,
                'label' => $unit->label.' - '.($unit->bedType?->name ?: 'Cama'),
                'space_label' => $this->spaceLabel($space),
                'room_label' => $this->roomName($room),
                'sale_mode' => $room->sale_mode,
                'renders_full_room_coverage' => $index === 0,
                'full_room_rowspan' => $index === 0 ? $room->bedUnits->where('status', 'active')->count() : 1,
                'cells' => $this->cells($dates, $blocks, $availabilityStatuses, $stays, $space->id, $room->id, $unit->id, $filters),
            ])
            ->values()
            ->all();

        $roomRow['cells'] = [];
        $roomRow['is_group_header'] = true;

        return [$roomRow, ...$bedRows];
    }

    private function completeRoomCells(array $cells): array
    {
        return collect($cells)
            ->map(function (array $cell): array {
                if (($cell['is_full_room_coverage'] ?? false) || ($cell['status'] ?? null) === 'available') {
                    return $cell;
                }

                return [
                    ...$cell,
                    'status' => 'available',
                    'label' => '',
                    'tone' => 'available',
                    'block_id' => null,
                    'stay_id' => null,
                    'check_in_group_id' => null,
                    'account_statement_id' => null,
                    'actions' => [],
                    'is_empty_group_cell' => true,
                ];
            })
            ->all();
    }

    private function cells(array $dates, Collection $blocks, Collection $availabilityStatuses, Collection $stays, int $spaceId, ?int $roomId, ?int $bedUnitId, array $filters): array
    {
        return collect($dates)
            ->map(function (array $date) use ($blocks, $availabilityStatuses, $stays, $spaceId, $roomId, $bedUnitId): array {
                $block = $this->blockForDate($blocks, $spaceId, $roomId, $bedUnitId, $date['date']);
                $availabilityStatus = $this->availabilityStatusForDate($availabilityStatuses, $spaceId, $roomId, $bedUnitId, $date['date']);
                $stay = $this->stayForDate($stays, $spaceId, $roomId, $bedUnitId, $date['date']);
                $isFullRoomStay = $stay !== null
                    && $roomId !== null
                    && $stay->room_bed_unit_id === null
                    && (int) ($stay->space_room_id ?? 0) === (int) $roomId;
                $isFullRoomBlock = $block !== null
                    && $roomId !== null
                    && $block->room_bed_unit_id === null
                    && (
                        (int) ($block->space_room_id ?? 0) === (int) $roomId
                        || $block->space_room_id === null
                    );
                $reservation = $this->reservationForBlock($block);
                $hasPartialBedBlock = $bedUnitId === null && $roomId !== null && ! $block && $this->hasBedUnitBlockForDate($blocks, $spaceId, $roomId, $date['date']);
                $blockedByAvailability = ! $stay && ! $block && (in_array($availabilityStatus?->status, ['closed', 'reserved', 'occupied'], true) || $hasPartialBedBlock);
                $isFullRoomAvailability = $bedUnitId === null
                    && $roomId !== null
                    && $availabilityStatus?->room_bed_unit_id === null
                    && in_array($availabilityStatus?->status, ['occupied', 'reserved'], true);
                $isCoveredByFullRoomAvailability = $bedUnitId !== null
                    && $roomId !== null
                    && $availabilityStatus?->room_bed_unit_id === null
                    && in_array($availabilityStatus?->status, ['occupied', 'reserved'], true);
                $status = $stay ? $stay->status : ($reservation || $hasPartialBedBlock ? 'reserved' : ($block?->type ?? ($availabilityStatus?->status ?? 'available')));
                $meta = self::STATUS_META[$status] ?? self::STATUS_META['available'];
                $guestName = $this->guestName($block);
                $tone = match (true) {
                    $stay !== null => $this->toneForStay($stay),
                    $reservation !== null => $this->toneForReservation($reservation),
                    $hasPartialBedBlock => 'reserved-unpaid',
                    $blockedByAvailability && $availabilityStatus?->status === 'reserved' => 'reserved-unpaid',
                    $blockedByAvailability => self::STATUS_META[$availabilityStatus?->status ?? 'closed']['tone'] ?? 'blocked',
                    default => $meta['tone'],
                };

                return [
                    'date' => $date['date'],
                    'status' => $status,
                    'label' => $stay ? $meta['label'] : ($guestName ?: ($hasPartialBedBlock ? 'Parcial' : $meta['label'])),
                    'status_label' => $meta['label'],
                    'tone' => $tone,
                    'block_id' => $block?->id,
                    'room_bed_unit_id' => $bedUnitId,
                    'guest_name' => $guestName,
                    'stay_id' => $stay?->id,
                    'check_in_group_id' => $stay?->check_in_group_id,
                    'check_in_code' => $stay?->checkInGroup?->code,
                    'holder_guest_name' => $this->holderGuestName($stay),
                    'account_statement_id' => $stay?->accountStatement?->id,
                    'reservation_id' => $reservation?->id,
                    'reservation_group_id' => $reservation?->reservation_group_id,
                    'title' => $block?->title,
                    'description' => $block?->description,
                    'start_date' => $block?->start_date?->toDateString(),
                    'end_date' => $block?->end_date?->toDateString(),
                    'closed_by_availability' => $availabilityStatus?->status === 'closed',
                    'blocked_by_availability' => $blockedByAvailability,
                    'availability_status_id' => $availabilityStatus?->id,
                    'availability_source' => $availabilityStatus?->source,
                    'is_full_room_coverage' => $isFullRoomStay || $isFullRoomBlock || $isFullRoomAvailability,
                    'is_covered_by_full_room' => $bedUnitId !== null && ($isFullRoomStay || $isFullRoomBlock || $isCoveredByFullRoomAvailability),
                    'actions' => $stay
                        ? $this->actionsForStay($stay)
                        : ($block
                        ? ($reservation ? ['move_reservation', 'extra_charge', 'view_reservation'] : ['view', 'edit', 'cancel'])
                        : ($blockedByAvailability ? [] : ['create_block', 'maintenance', 'owner_use', 'unavailable'])),
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
                ?: $matchingBlocks->first(fn (OccupancyBlock $block): bool => (int) ($block->space_room_id ?? 0) === (int) $roomId && $block->room_bed_unit_id === null)
                ?: $matchingBlocks->first(fn (OccupancyBlock $block): bool => $block->space_room_id === null && $block->room_bed_unit_id === null);
        }

        return $matchingBlocks->first(fn (OccupancyBlock $block): bool => (int) ($block->space_room_id ?? 0) === (int) ($roomId ?? 0) && $block->room_bed_unit_id === null)
            ?: $matchingBlocks->first(fn (OccupancyBlock $block): bool => $block->space_room_id === null && $block->room_bed_unit_id === null);
    }

    private function availabilityStatusForDate(Collection $statuses, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?AvailabilityStatus
    {
        if ($bedUnitId !== null) {
            return $statuses->get($this->availabilityKey($spaceId, $roomId, $date, $bedUnitId))
                ?: $statuses->get($this->availabilityKey($spaceId, $roomId, $date));
        }

        return $statuses->get($this->availabilityKey($spaceId, $roomId, $date));
    }

    private function hasBedUnitBlockForDate(Collection $blocks, int $spaceId, int $roomId, string $date): bool
    {
        return $blocks->contains(fn (OccupancyBlock $block): bool => (int) $block->space_id === $spaceId
            && (int) ($block->space_room_id ?? 0) === $roomId
            && $block->room_bed_unit_id !== null
            && $block->start_date->toDateString() <= $date
            && $block->end_date->toDateString() >= $date);
    }

    private function stayForDate(Collection $stays, int $spaceId, ?int $roomId, ?int $bedUnitId, string $date): ?Stay
    {
        return $stays->first(function (Stay $stay) use ($spaceId, $roomId, $bedUnitId, $date): bool {
            if ((int) $stay->space_id !== $spaceId) {
                return false;
            }

            if ($stay->check_in_date->toDateString() > $date || $stay->check_out_date->toDateString() <= $date) {
                return false;
            }

            if ($bedUnitId !== null) {
                return (int) ($stay->room_bed_unit_id ?? 0) === $bedUnitId
                    || ((int) ($stay->space_room_id ?? 0) === (int) $roomId && $stay->room_bed_unit_id === null);
            }

            return (int) ($stay->space_room_id ?? 0) === (int) ($roomId ?? 0)
                && $stay->room_bed_unit_id === null;
        });
    }

    private function availabilityKey(int $spaceId, ?int $roomId, string $date, ?int $bedUnitId = null): string
    {
        return $spaceId.'|'.($roomId ?? 'space').'|'.($bedUnitId ? 'bed:'.$bedUnitId : 'room').'|'.$date;
    }

    private function guestName(?OccupancyBlock $block): ?string
    {
        return $block?->reservation?->guest_name
            ?: $block?->reservationRoom?->reservation?->guest_name
            ?: $block?->reservationBedUnit?->guest_name
            ?: $block?->reservationBedUnit?->reservation?->guest_name;
    }

    private function reservationForBlock(?OccupancyBlock $block)
    {
        return $block?->reservation
            ?: $block?->reservationRoom?->reservation
            ?: $block?->reservationBedUnit?->reservation;
    }

    private function toneForStay(Stay $stay): string
    {
        if ($stay->status === 'checked_out') {
            return 'checkout-clean';
        }

        return (float) ($stay->accountStatement?->balance ?? 0) > 0
            ? 'occupied-debt'
            : 'occupied-paid';
    }

    private function actionsForStay(Stay $stay): array
    {
        if ($stay->status === 'checked_out') {
            return ['view_check_in', 'view_account'];
        }

        return ['view_check_in', 'move_stay', 'edit_stay', 'view_account', 'extra_charge', 'check_out'];
    }

    private function toneForReservation($reservation): string
    {
        $hasAdvance = in_array($reservation->payment_status, ['submitted', 'validated'], true)
            || in_array($reservation->status, ['payment_under_review', 'confirmed'], true);

        return $hasAdvance ? 'reserved-paid' : 'reserved-unpaid';
    }

    private function holderGuestName(?Stay $stay): ?string
    {
        if (! $stay?->holderGuest) {
            return null;
        }

        return trim($stay->holderGuest->first_name.' '.$stay->holderGuest->last_name) ?: null;
    }

    private function applyViewFilter(Builder $query, array $filters): Builder
    {
        $view = (string) ($filters['view'] ?? 'private');

        if (str_starts_with($view, 'shared:')) {
            return $query->where('space_id', (int) Str::after($view, 'shared:'));
        }

        return $query->whereHas('space.spaceMode', fn (Builder $spaceMode): Builder => $spaceMode->where('slug', 'privado'));
    }

    private function summary(Collection $spaces, Collection $blocks): array
    {
        return [
            'private_spaces' => $spaces->where('spaceMode.slug', 'privado')->count(),
            'shared_rooms' => $spaces
                ->where('spaceMode.slug', 'compartido')
                ->sum(fn (Space $space): int => $space->rooms->count()),
            'blocks_this_week' => $blocks->count(),
        ];
    }

    private function spaceLabel(Space $space): string
    {
        $label = $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title ?: 'Alojamiento')
            : ($space->title ?: $space->name ?: 'Alojamiento');

        if ($space->spaceMode?->slug !== 'compartido' && (int) $space->max_capacity > 0) {
            return $label.' - '.$space->max_capacity;
        }

        return $label;
    }

    private function roomLabel(SpaceRoom $room): string
    {
        $label = $this->roomName($room);
        $beds = $room->beds
            ->map(fn ($bed): string => trim($bed->quantity.' '.($bed->bedType?->name ?: 'cama')))
            ->filter()
            ->implode(', ');

        return filled($beds) ? $label.' - '.$beds : $label;
    }

    private function roomName(SpaceRoom $room): string
    {
        return $room->name ?: $room->title ?: 'Habitacion';
    }
}
