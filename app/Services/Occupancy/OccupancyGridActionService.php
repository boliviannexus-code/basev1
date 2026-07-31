<?php

namespace App\Services\Occupancy;

use App\Models\AvailabilityStatus;
use App\Models\OccupancyBlock;
use App\Models\Reservation;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class OccupancyGridActionService
{
    public const ACTION_CHECK_IN = 'check_in';

    public const ACTION_CHECK_OUT = 'check_out';

    public const ACTION_RESERVATION = 'reservation';

    public const ACTION_BLOCK = 'block';

    public const ACTION_EXTRA_CHARGE = 'extra_charge';

    public function cellContext(int $companyId, array $data): array
    {
        $date = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $space = Space::query()
            ->with('spaceMode')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($data['space_id'])
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                'space_id' => 'La habitacion o espacio debe pertenecer a tu empresa.',
            ]);
        }

        $room = $this->resolveRoom($companyId, $space, $data['space_room_id'] ?? null);
        $bedUnit = $this->resolveBedUnit($companyId, $space, $room, $data['room_bed_unit_id'] ?? null);
        $block = $this->currentBlock($companyId, $space, $room, $bedUnit, $date);
        $availabilityStatus = $this->currentAvailabilityStatus($companyId, $space, $room, $bedUnit, $date);
        $stay = $this->currentStay($companyId, $space, $room, $bedUnit, $date);
        $pendingCheckOutStay = $stay ? null : $this->pendingCheckOutStay($companyId, $space, $room, $bedUnit, $date);
        $reservation = $this->reservationForBlock($block);
        $occupancyState = $this->occupancyState($block, $availabilityStatus, $stay ?: $pendingCheckOutStay, $reservation, $pendingCheckOutStay !== null);

        return [
            'space' => $space,
            'room' => $room,
            'bed_unit' => $bedUnit,
            'stay' => $stay ?: $pendingCheckOutStay,
            'date' => $date,
            'resource_id' => $bedUnit?->id ?? $room?->id ?? $space->id,
            'resource_name' => $bedUnit ? $this->roomLabel($room).' / '.$bedUnit->label : ($room ? $this->roomLabel($room) : $this->spaceLabel($space)),
            'bed_type_label' => $this->bedTypeLabel($room, $bedUnit),
            'availability_status' => $availabilityStatus,
            'occupancy_state' => $occupancyState,
            'reservation' => $reservation,
            'actions' => $this->actionsForDate($date, $availabilityStatus, $occupancyState, $stay ?: $pendingCheckOutStay, $reservation),
        ];
    }

    public function ensureActionAllowed(string $action, Carbon $date, ?AvailabilityStatus $availabilityStatus = null, ?array $occupancyState = null, ?Stay $stay = null): void
    {
        $allowed = collect($this->actionsForDate($date, $availabilityStatus, $occupancyState, $stay))->pluck('key')->contains($action);

        if (! $allowed) {
            throw ValidationException::withMessages([
                'date' => match ($action) {
                    self::ACTION_CHECK_IN => 'El check-in solo esta permitido para la fecha de hoy.',
                    self::ACTION_CHECK_OUT => 'El check-out solo esta permitido hoy cuando el espacio esta ocupado.',
                    default => 'No se pueden gestionar acciones para fechas pasadas.',
                },
            ]);
        }
    }

    public function actionsForDate(Carbon $date, ?AvailabilityStatus $availabilityStatus = null, ?array $occupancyState = null, ?Stay $stay = null, ?Reservation $reservation = null): array
    {
        $today = today();

        if (($occupancyState['status'] ?? null) === 'pending_check_out') {
            return $this->pendingCheckOutActions($date, $availabilityStatus);
        }

        if ($stay) {
            return $this->stayActions($stay);
        }

        if ($reservation) {
            $actions = [
                [
                    'key' => 'move_reservation',
                    'label' => 'Mover reserva',
                    'icon' => 'ti-switch-horizontal',
                    'tone' => 'primary',
                    'disabled' => ! $reservation->shouldBlockAvailability(),
                ],
                [
                    'key' => self::ACTION_EXTRA_CHARGE,
                    'label' => 'Agregar cargo extra',
                    'icon' => 'ti-plus',
                    'tone' => 'primary',
                ],
            ];

            if ($reservation->reservation_group_id) {
                $actions[] = [
                    'key' => 'collect_reservation_payment',
                    'label' => 'Cobrar',
                    'icon' => 'ti-cash-register',
                    'tone' => 'success',
                ];
            }

            $actions[] = [
                'key' => 'view_reservation',
                'label' => 'Ver reserva',
                'icon' => 'ti-calendar-check',
                'tone' => 'success',
                'url' => $reservation->reservation_group_id
                    ? route('admin.reservation-groups.show', $reservation->reservation_group_id)
                    : route('admin.reservations.show', $reservation),
                'modal_url' => $reservation->reservation_group_id
                    ? route('admin.reservation-groups.show', $reservation->reservation_group_id)
                    : route('admin.reservations.show', $reservation),
                'modal_title' => 'Reserva '.$reservation->code,
                'modal_size' => 'xl',
            ];

            return $actions;
        }

        if ($date->lt($today) || in_array($availabilityStatus?->status, ['closed', 'reserved'], true)) {
            return [];
        }

        $actions = [];

        if ($date->isSameDay($today)) {
            $actions[] = [
                'key' => self::ACTION_CHECK_IN,
                'label' => 'Check-in',
                'icon' => 'ti-login',
                'tone' => 'primary',
            ];
        }

        return [
            ...$actions,
            [
                'key' => self::ACTION_RESERVATION,
                'label' => 'Reserva',
                'icon' => 'ti-calendar-plus',
                'tone' => 'success',
            ],
            [
                'key' => self::ACTION_BLOCK,
                'label' => 'Bloqueo',
                'icon' => 'ti-lock',
                'tone' => 'secondary',
            ],
        ];
    }

    private function pendingCheckOutActions(Carbon $date, ?AvailabilityStatus $availabilityStatus = null): array
    {
        if (! $date->isSameDay(today()) || in_array($availabilityStatus?->status, ['closed', 'reserved'], true)) {
            return [];
        }

        return [
            [
                'key' => self::ACTION_CHECK_OUT,
                'label' => 'Check-out',
                'icon' => 'ti-logout',
                'tone' => 'warning',
            ],
            [
                'key' => self::ACTION_RESERVATION,
                'label' => 'Reserva',
                'icon' => 'ti-calendar-plus',
                'tone' => 'success',
            ],
            [
                'key' => self::ACTION_BLOCK,
                'label' => 'Bloqueo',
                'icon' => 'ti-lock',
                'tone' => 'secondary',
            ],
        ];
    }

    private function stayActions(Stay $stay): array
    {
        $base = [
            [
                'key' => 'view_check_in',
                'label' => 'Ver check-in',
                'icon' => 'ti-eye',
                'tone' => 'primary',
            ],
            [
                'key' => 'view_account',
                'label' => 'Estado de cuenta',
                'icon' => 'ti-receipt-2',
                'tone' => 'success',
                'url' => route('stays.account', $stay),
            ],
        ];

        if ($stay->status === 'checked_out') {
            return $base;
        }

        return [
            $base[0],
            [
                'key' => 'move_stay',
                'label' => 'Cambiar habitacion',
                'icon' => 'ti-switch-horizontal',
                'tone' => 'primary',
            ],
            [
                'key' => 'edit_stay',
                'label' => 'Editar estancia',
                'icon' => 'ti-edit',
                'tone' => 'secondary',
                'url' => route('check-ins.edit', [
                    'checkInGroup' => $stay->check_in_group_id,
                    'highlight_stay' => $stay->id,
                ]).'#stay-'.$stay->id,
            ],
            $base[1],
            [
                'key' => 'collect_stay_payment',
                'label' => 'Cobrar',
                'icon' => 'ti-cash-register',
                'tone' => 'success',
            ],
            [
                'key' => self::ACTION_EXTRA_CHARGE,
                'label' => 'Agregar cargo extra',
                'icon' => 'ti-plus',
                'tone' => 'primary',
            ],
            [
                'key' => self::ACTION_CHECK_OUT,
                'label' => 'Check-out',
                'icon' => 'ti-logout',
                'tone' => 'warning',
            ],
        ];
    }

    private function resolveRoom(int $companyId, Space $space, mixed $roomId): ?SpaceRoom
    {
        $isShared = $space->spaceMode?->slug === 'compartido';

        if (! $isShared) {
            if (filled($roomId)) {
                throw ValidationException::withMessages([
                    'space_room_id' => 'Un espacio privado no permite seleccionar habitacion.',
                ]);
            }

            return null;
        }

        if (! filled($roomId)) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Selecciona una habitacion activa para este espacio compartido.',
            ]);
        }

        $room = SpaceRoom::query()
            ->with('beds.bedType')
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereKey($roomId)
            ->first();

        if (! $room) {
            throw ValidationException::withMessages([
                'space_room_id' => 'La habitacion seleccionada no pertenece a tu empresa.',
            ]);
        }

        return $room;
    }

    private function resolveBedUnit(int $companyId, Space $space, ?SpaceRoom $room, mixed $bedUnitId): ?RoomBedUnit
    {
        if (! filled($bedUnitId)) {
            return null;
        }

        if (! $room) {
            throw ValidationException::withMessages([
                'room_bed_unit_id' => 'Selecciona una habitacion antes de seleccionar cama.',
            ]);
        }

        $bedUnit = RoomBedUnit::query()
            ->with('bedType')
            ->where('company_id', $companyId)
            ->where('space_room_id', $room->id)
            ->where('status', 'active')
            ->whereKey($bedUnitId)
            ->first();

        if (! $bedUnit || (int) $room->space_id !== (int) $space->id) {
            throw ValidationException::withMessages([
                'room_bed_unit_id' => 'La cama seleccionada no pertenece a tu empresa.',
            ]);
        }

        return $bedUnit;
    }

    private function currentBlock(int $companyId, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, Carbon $date): ?OccupancyBlock
    {
        $query = OccupancyBlock::query()
            ->with(['reservation', 'reservationRoom.reservation', 'reservationBedUnit.reservation'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString());

        if ($bedUnit) {
            return (clone $query)->where('room_bed_unit_id', $bedUnit->id)->first()
                ?: (clone $query)->where('space_room_id', $room->id)->whereNull('room_bed_unit_id')->first();
        }

        return $query
            ->when(
                $room,
                fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn ($query) => $query->where('space_id', $space->id)->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->first();
    }

    private function currentAvailabilityStatus(int $companyId, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, Carbon $date): ?AvailabilityStatus
    {
        $query = AvailabilityStatus::query()
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('date', $date->toDateString());

        if ($bedUnit) {
            return (clone $query)->where('room_bed_unit_id', $bedUnit->id)->first()
                ?: (clone $query)->where('space_room_id', $room->id)->whereNull('room_bed_unit_id')->first();
        }

        return $query
            ->when(
                $room,
                fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn ($query) => $query->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->first();
    }

    private function currentStay(int $companyId, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, Carbon $date): ?Stay
    {
        $query = Stay::query()
            ->with(['holderGuest', 'checkInGroup', 'accountStatement'])
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->whereDate('check_in_date', '<=', $date->toDateString())
            ->whereDate('check_out_date', '>', $date->toDateString());

        if ($bedUnit) {
            return (clone $query)->where('room_bed_unit_id', $bedUnit->id)->first()
                ?: (clone $query)->where('space_room_id', $room->id)->whereNull('room_bed_unit_id')->first();
        }

        return $query
            ->when(
                $room,
                fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn ($query) => $query->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->first();
    }

    private function pendingCheckOutStay(int $companyId, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, Carbon $date): ?Stay
    {
        if (! $date->isToday()) {
            return null;
        }

        $query = Stay::query()
            ->with(['holderGuest', 'checkInGroup', 'accountStatement'])
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('status', 'occupied')
            ->whereDate('check_out_date', $date->toDateString());

        if ($bedUnit) {
            return (clone $query)->where('room_bed_unit_id', $bedUnit->id)->first()
                ?: (clone $query)->where('space_room_id', $room->id)->whereNull('room_bed_unit_id')->first();
        }

        return $query
            ->when(
                $room,
                fn ($query) => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn ($query) => $query->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->first();
    }

    private function occupancyState(?OccupancyBlock $block, ?AvailabilityStatus $availabilityStatus, ?Stay $stay = null, ?Reservation $reservation = null, bool $pendingCheckOut = false): array
    {
        if ($pendingCheckOut && $stay) {
            return [
                'status' => 'pending_check_out',
                'label' => 'Pendiente de check-out',
                'block_id' => null,
                'stay_id' => $stay->id,
                'check_in_group_id' => $stay->check_in_group_id,
                'check_in_code' => $stay->checkInGroup?->code,
                'holder_guest_name' => trim($stay->holderGuest?->first_name.' '.$stay->holderGuest?->last_name) ?: null,
                'account_statement_id' => $stay->accountStatement?->id,
                'reservation_id' => null,
                'availability_status_id' => $availabilityStatus?->id,
                'availability_source' => $availabilityStatus?->source,
                'message' => 'Habitacion pendiente de check out.',
            ];
        }

        if ($stay) {
            $meta = OccupancyGridService::STATUS_META[$stay->status] ?? OccupancyGridService::STATUS_META['occupied'];

            return [
                'status' => $stay->status,
                'label' => $meta['label'],
                'block_id' => null,
                'stay_id' => $stay->id,
                'check_in_group_id' => $stay->check_in_group_id,
                'check_in_code' => $stay->checkInGroup?->code,
                'holder_guest_name' => trim($stay->holderGuest?->first_name.' '.$stay->holderGuest?->last_name) ?: null,
                'account_statement_id' => $stay->accountStatement?->id,
                'reservation_id' => null,
                'availability_status_id' => $availabilityStatus?->id,
                'availability_source' => $availabilityStatus?->source,
            ];
        }

        if (! $block) {
            if ($availabilityStatus && $availabilityStatus->status !== 'available') {
                $meta = OccupancyGridService::STATUS_META[$availabilityStatus->status] ?? OccupancyGridService::STATUS_META['available'];

                return [
                    'status' => $availabilityStatus->status,
                    'label' => $meta['label'],
                    'block_id' => null,
                    'availability_status_id' => $availabilityStatus->id,
                    'availability_source' => $availabilityStatus->source,
                ];
            }

            return [
                'status' => 'available',
                'label' => 'Libre',
                'block_id' => null,
                'availability_status_id' => null,
            ];
        }

        $meta = $reservation
            ? OccupancyGridService::STATUS_META['reserved']
            : (OccupancyGridService::STATUS_META[$block->type] ?? OccupancyGridService::STATUS_META['manual_block']);

        return [
            'status' => $reservation ? 'reserved' : $block->type,
            'label' => $meta['label'],
            'block_id' => $block->id,
            'reservation_id' => $reservation?->id,
            'reservation_group_id' => $reservation?->reservation_group_id,
            'title' => $block->title,
            'description' => $block->description,
            'start_date' => $block->start_date?->toDateString(),
            'end_date' => $block->end_date?->toDateString(),
        ];
    }

    private function reservationForBlock(?OccupancyBlock $block): ?Reservation
    {
        return $block?->reservation
            ?: $block?->reservationRoom?->reservation
            ?: $block?->reservationBedUnit?->reservation;
    }

    private function spaceLabel(Space $space): string
    {
        return $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title ?: 'Alojamiento')
            : ($space->title ?: $space->name ?: 'Alojamiento');
    }

    private function roomLabel(SpaceRoom $room): string
    {
        $label = $room->name ?: $room->title ?: 'Habitacion';

        return str_starts_with(mb_strtolower($label), 'hab ')
            || str_starts_with(mb_strtolower($label), 'habitacion')
            ? $label
            : 'Hab '.$label;
    }

    private function bedTypeLabel(?SpaceRoom $room, ?RoomBedUnit $bedUnit): ?string
    {
        if ($bedUnit) {
            return $bedUnit->bedType?->name ?: 'Cama';
        }

        if (! $room) {
            return null;
        }

        return $room->beds
            ->map(fn ($bed): ?string => $bed->bedType?->name
                ? $bed->quantity.' '.$bed->bedType->name
                : null)
            ->filter()
            ->unique()
            ->implode(' · ') ?: null;
    }
}
