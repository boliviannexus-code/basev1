<?php

namespace App\Services\Reservations;

use App\Models\AvailabilityDay;
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
use App\Services\CheckIn\CurrencyConversionService;
use App\Services\CheckIn\GuestService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InternalReservationService
{
    public function availableResources(int $companyId, string $checkIn, string $checkOut): array
    {
        $this->validateDates($checkIn, $checkOut);
        $nightDates = $this->nightDates($checkIn, $checkOut);

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
            ->get()
            ->flatMap(fn (Space $space): array => $this->resourceOptionsForSpace($space, $nightDates, $checkIn, $checkOut))
            ->filter(fn (array $resource): bool => $resource['available'])
            ->values()
            ->all();
    }

    public function create(int $companyId, array $data, ?User $user = null): ReservationGroup
    {
        if (array_key_exists('stays', $data)) {
            return $this->createFromCheckInPayload($companyId, $data, $user);
        }

        $this->validateDates($data['check_in'], $data['check_out']);

        return DB::transaction(function () use ($companyId, $data, $user): ReservationGroup {
            $nightDates = $this->nightDates($data['check_in'], $data['check_out']);
            $resources = collect($data['resources'])
                ->map(fn (array $resource, int $index): array => $this->quoteResource($companyId, $resource, $nightDates, $data['check_in'], $data['check_out'], $index))
                ->values();

            if ($resources->isEmpty()) {
                throw ValidationException::withMessages([
                    'resources' => 'Selecciona al menos un recurso para reservar.',
                ]);
            }

            $this->ensureResourcesDoNotOverlap($resources);

            $capacity = (int) $resources->sum('capacity');
            if ((int) $data['guests'] > $capacity) {
                throw ValidationException::withMessages([
                    'guests' => 'La cantidad de personas supera la capacidad total seleccionada.',
                ]);
            }

            $total = round((float) $resources->sum('total_amount'), 2);

            $group = ReservationGroup::query()->create([
                'company_id' => $companyId,
                'reservation_channel_id' => $data['reservation_channel_id'] ?? null,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'] ?? null,
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_document' => $data['guest_document'] ?? null,
                'guest_document_type' => $data['document_type'] ?? null,
                'guest_birth_country_id' => $data['birth_country_id'] ?? null,
                'guest_birth_date' => $data['birth_date'] ?? null,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'nights' => count($nightDates),
                'guests' => (int) $data['guests'],
                'subtotal_amount' => $total,
                'total_amount' => $total,
                'advance_amount' => 0,
                'balance_amount' => $total,
                'currency' => 'BOB',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($resources as $resource) {
                $this->createChildReservation($group, $resource, $data, count($nightDates), $user);
            }

            $this->accountStatements->createForReservationGroup($group->refresh()->load('reservations'));

            return $group->refresh()->load([
                'accountStatement.items',
                'reservationChannel',
                'reservations.space',
                'reservations.room',
                'reservations.rooms',
                'reservations.roomItems.room',
                'reservations.bedUnitItems.bedUnit.room',
            ]);
        });
    }

    public function __construct(
        private readonly GuestService $guests,
        private readonly CurrencyConversionService $currency,
        private readonly AccountStatementService $accountStatements,
    ) {}

    private function createFromCheckInPayload(int $companyId, array $data, ?User $user = null): ReservationGroup
    {
        $this->validateDates($data['check_in_date'], $data['check_out_date']);

        return DB::transaction(function () use ($companyId, $data, $user): ReservationGroup {
            $holder = $this->guests->findOrCreateHolder($companyId, $data['main_guest'], $user?->id);
            $nightDates = $this->nightDates($data['check_in_date'], $data['check_out_date']);
            $resources = collect($data['stays'])
                ->map(function (array $stay, int $index) use ($companyId, $nightDates, $data): array {
                    $stay = $this->currency->normalizeStayPrices($stay, $companyId);
                    $resource = $this->quoteResource($companyId, [
                        'resource_type' => $stay['resource_type'],
                        'space_id' => $stay['space_id'],
                        'space_room_id' => $stay['space_room_id'] ?? null,
                        'room_bed_unit_id' => $stay['room_bed_unit_id'] ?? null,
                        'price_per_night' => $stay['price_per_night_bob'],
                    ], $nightDates, $data['check_in_date'], $data['check_out_date'], $index);

                    return [
                        ...$resource,
                        'stay' => $stay,
                    ];
                })
                ->values();

            if ($resources->isEmpty()) {
                throw ValidationException::withMessages([
                    'stays' => 'Selecciona al menos un recurso para reservar.',
                ]);
            }

            $this->ensureResourcesDoNotOverlap($resources);

            $capacity = (int) $resources->sum(fn (array $resource): int => (int) ($resource['stay']['people_count'] ?? $resource['capacity']));
            if ((int) $data['total_people'] > $capacity) {
                throw ValidationException::withMessages([
                    'total_people' => 'La cantidad de personas supera la capacidad total seleccionada.',
                ]);
            }

            $total = round((float) $resources->sum('total_amount'), 2);

            $guestName = trim($holder->first_name.' '.$holder->last_name);
            $group = ReservationGroup::query()->create([
                'company_id' => $companyId,
                'reservation_channel_id' => $data['reservation_channel_id'] ?? null,
                'guest_name' => $guestName,
                'guest_email' => null,
                'guest_phone' => $holder->phone,
                'guest_document' => $holder->document_number,
                'guest_document_type' => $holder->document_type,
                'guest_birth_country_id' => $holder->birth_country_id,
                'guest_birth_date' => $holder->birth_date,
                'check_in' => $data['check_in_date'],
                'check_out' => $data['check_out_date'],
                'nights' => count($nightDates),
                'guests' => (int) $data['total_people'],
                'subtotal_amount' => $total,
                'total_amount' => $total,
                'advance_amount' => 0,
                'balance_amount' => $total,
                'currency' => 'BOB',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_method' => null,
                'payment_reference' => null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($resources as $resource) {
                $this->createChildReservation($group, $resource, [
                    'check_in' => $data['check_in_date'],
                    'check_out' => $data['check_out_date'],
                    'breakfast_included' => (bool) ($resource['stay']['breakfast_included'] ?? false),
                ], count($nightDates), $user);
            }

            $this->accountStatements->createForReservationGroup($group->refresh()->load('reservations'));

            return $group->refresh()->load([
                'accountStatement.items',
                'reservationChannel',
                'reservations.space',
                'reservations.room',
                'reservations.rooms',
                'reservations.roomItems.room',
                'reservations.bedUnitItems.bedUnit.room',
            ]);
        });
    }

    private function resourceOptionsForSpace(Space $space, array $nightDates, string $checkIn, string $checkOut): array
    {
        if ($space->spaceMode?->slug !== 'compartido') {
            return [$this->resourcePayload($space, null, null, $nightDates, $checkIn, $checkOut)];
        }

        return $space->rooms
            ->flatMap(function (SpaceRoom $room) use ($space, $nightDates, $checkIn, $checkOut): array {
                $resources = [];

                if (in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                    $resources[] = $this->resourcePayload($space, $room, null, $nightDates, $checkIn, $checkOut);
                }

                if (in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                    foreach ($room->bedUnits->where('status', 'active') as $bedUnit) {
                        $resources[] = $this->resourcePayload($space, $room, $bedUnit, $nightDates, $checkIn, $checkOut);
                    }
                }

                return $resources;
            })
            ->all();
    }

    private function resourcePayload(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, array $nightDates, string $checkIn, string $checkOut): array
    {
        $available = $this->isResourceAvailable($space, $room, $bedUnit, $checkIn, $checkOut);
        $defaultPrice = $this->defaultNightlyPrice($space, $room, $nightDates);
        $capacity = $this->capacity($space, $room, $bedUnit);
        $type = $bedUnit ? 'shared_bed_unit' : ($room ? 'shared_room' : 'private_space');
        $label = collect([
            $space->spaceMode?->slug === 'compartido' ? ($space->name ?: $space->title) : ($space->title ?: $space->name),
            $room ? ($room->name ?: $room->title) : null,
            $bedUnit?->label,
        ])->filter()->implode(' / ');

        return [
            'key' => $bedUnit ? 'bed:'.$bedUnit->id : (($room ? 'room:' : 'space:').($room?->id ?? $space->id)),
            'resource_type' => $type,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'label' => $label,
            'capacity' => $capacity,
            'default_price_per_night' => $defaultPrice,
            'status' => $available ? 'available' : 'closed',
            'disabled' => ! $available,
            'sale_mode' => $room?->sale_mode,
            'full_room_key' => $room && $bedUnit && $room->sale_mode === 'flexible' ? 'room:'.$room->id : null,
            'full_room_available' => $room && $room->sale_mode === 'flexible' && $this->isResourceAvailable($space, $room, null, $checkIn, $checkOut),
            'full_room_capacity' => $room ? $this->capacity($space, $room, null) : null,
            'full_room_status' => $room && $room->sale_mode === 'flexible' && $this->isResourceAvailable($space, $room, null, $checkIn, $checkOut) ? 'available' : 'closed',
            'available' => $available,
        ];
    }

    private function quoteResource(int $companyId, array $data, array $nightDates, string $checkIn, string $checkOut, int $index): array
    {
        [$space, $room, $bedUnit] = $this->resolveResource($companyId, $data, $index);

        if (! $this->isResourceAvailable($space, $room, $bedUnit, $checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                "resources.{$index}.resource_key" => 'El recurso seleccionado ya no esta disponible.',
            ]);
        }

        $price = round((float) ($data['price_per_night'] ?? 0), 2);
        if ($price < 0) {
            throw ValidationException::withMessages([
                "resources.{$index}.price_per_night" => 'El precio no puede ser negativo.',
            ]);
        }

        return [
            'space' => $space,
            'room' => $room,
            'bed_unit' => $bedUnit,
            'resource_type' => $bedUnit ? 'shared_bed_unit' : ($room ? 'shared_room' : 'private_space'),
            'capacity' => $this->capacity($space, $room, $bedUnit),
            'price_per_night' => $price,
            'total_amount' => round($price * count($nightDates), 2),
        ];
    }

    private function createChildReservation(ReservationGroup $group, array $resource, array $data, int $nights, ?User $user): Reservation
    {
        $space = $resource['space'];
        $room = $resource['room'];
        $bedUnit = $resource['bed_unit'];
        $lastNight = CarbonImmutable::parse($data['check_out'])->subDay()->toDateString();
        $title = 'Reserva '.$group->code.' pendiente de pago';

        $block = OccupancyBlock::query()->create([
            'company_id' => $group->company_id,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'type' => 'unavailable',
            'status' => 'active',
            'title' => $title,
            'description' => 'Bloqueo generado desde reserva interna.',
            'start_date' => $data['check_in'],
            'end_date' => $lastNight,
            'created_by' => $user?->id,
        ]);

        $reservation = Reservation::query()->create([
            'company_id' => $group->company_id,
            'reservation_group_id' => $group->id,
            'user_id' => null,
            'space_id' => $space->id,
            'space_room_id' => $room && ! $bedUnit ? $room->id : null,
            'occupancy_block_id' => $block->id,
            'reservation_channel_id' => $group->reservation_channel_id,
            'code' => $this->reservationCode(),
            'guest_name' => $group->guest_name,
            'guest_email' => $group->guest_email ?: 'reserva-'.$group->id.'-'.$space->id.'@internal.local',
            'guest_phone' => $group->guest_phone,
            'guest_document' => $group->guest_document,
            'guest_document_type' => $group->guest_document_type,
            'guest_birth_country_id' => $group->guest_birth_country_id,
            'guest_birth_date' => $group->guest_birth_date,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'nights' => $nights,
            'guests' => $group->guests,
            'price_per_person' => $resource['price_per_night'],
            'subtotal_amount' => $resource['total_amount'],
            'total_amount' => $resource['total_amount'],
            'advance_amount' => 0,
            'deposit_amount' => 0,
            'balance_amount' => $resource['total_amount'],
            'currency' => 'BOB',
            'breakfast_included' => (bool) ($data['breakfast_included'] ?? false),
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => 'internal',
            'hold_expires_at' => null,
            'guest_notes' => $group->notes,
        ]);

        if ($bedUnit) {
            ReservationBedUnit::query()->create([
                'reservation_id' => $reservation->id,
                'room_bed_unit_id' => $bedUnit->id,
                'occupancy_block_id' => $block->id,
                'guest_name' => $group->guest_name,
                'price_per_night' => $resource['price_per_night'],
                'subtotal_amount' => $resource['total_amount'],
            ]);
        } elseif ($room) {
            ReservationRoom::query()->create([
                'reservation_id' => $reservation->id,
                'space_room_id' => $room->id,
                'occupancy_block_id' => $block->id,
                'capacity' => $resource['capacity'],
                'price_per_night' => $resource['price_per_night'],
                'subtotal_amount' => $resource['total_amount'],
            ]);
        }

        return $reservation;
    }

    private function ensureResourcesDoNotOverlap(Collection $resources): void
    {
        $keys = $resources->map(fn (array $resource): string => implode(':', [
            $resource['resource_type'],
            $resource['space']->id,
            $resource['room']?->id ?? '',
            $resource['bed_unit']?->id ?? '',
        ]));

        if ($keys->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'resources' => 'No puedes seleccionar el mismo recurso mas de una vez.',
            ]);
        }

        $fullRoomIds = $resources
            ->filter(fn (array $resource): bool => $resource['resource_type'] === 'shared_room')
            ->map(fn (array $resource): int => (int) $resource['room']->id);
        $hasBedInsideFullRoom = $resources
            ->filter(fn (array $resource): bool => $resource['resource_type'] === 'shared_bed_unit')
            ->contains(fn (array $resource): bool => $fullRoomIds->contains((int) $resource['room']->id));

        if ($hasBedInsideFullRoom) {
            throw ValidationException::withMessages([
                'resources' => 'No puedes reservar una habitacion completa y una de sus camas al mismo tiempo.',
            ]);
        }
    }

    private function resolveResource(int $companyId, array $data, int $index): array
    {
        $type = $data['resource_type'] ?? null;
        $space = Space::query()
            ->with(['spaceMode', 'rooms.beds.bedType', 'rooms.bedUnits.bedType'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereKey($data['space_id'] ?? null)
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                "resources.{$index}.space_id" => 'El alojamiento seleccionado no esta disponible.',
            ]);
        }

        if ($type === 'private_space') {
            if ($space->spaceMode?->slug === 'compartido') {
                throw ValidationException::withMessages(["resources.{$index}.resource_type" => 'El recurso no corresponde al alojamiento.']);
            }

            return [$space, null, null];
        }

        $room = SpaceRoom::query()
            ->with(['beds.bedType', 'bedUnits.bedType'])
            ->where('company_id', $companyId)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereKey($data['space_room_id'] ?? null)
            ->first();

        if (! $room) {
            throw ValidationException::withMessages(["resources.{$index}.space_room_id" => 'Selecciona una habitacion valida.']);
        }

        if ($type === 'shared_room') {
            if (! in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                throw ValidationException::withMessages(["resources.{$index}.resource_type" => 'La habitacion no se vende completa.']);
            }

            return [$space, $room, null];
        }

        $bedUnit = RoomBedUnit::query()
            ->with('bedType')
            ->where('company_id', $companyId)
            ->where('space_room_id', $room->id)
            ->where('status', 'active')
            ->whereKey($data['room_bed_unit_id'] ?? null)
            ->first();

        if ($type !== 'shared_bed_unit' || ! $bedUnit) {
            throw ValidationException::withMessages(["resources.{$index}.room_bed_unit_id" => 'Selecciona una cama valida.']);
        }

        if (! in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
            throw ValidationException::withMessages(["resources.{$index}.resource_type" => 'La habitacion no se vende por cama.']);
        }

        return [$space, $room, $bedUnit];
    }

    private function isResourceAvailable(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return ! $this->hasStay($space, $room, $bedUnit, $checkIn, $checkOut)
            && ! $this->hasBlock($space, $room, $bedUnit, $checkIn, $checkOut)
            && ! $this->hasReservation($space, $room, $bedUnit, $checkIn, $checkOut)
            && ! $this->hasBlockingAvailability($space, $room, $bedUnit, $checkIn, $checkOut);
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

    private function hasBlock(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return OccupancyBlock::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('status', 'active')
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

    private function hasReservation(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        return Reservation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
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
        $dates = $this->nightDates($checkIn, $checkOut);

        return AvailabilityStatus::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->whereIn('date', $dates)
            ->whereIn('status', ['closed', 'reserved'])
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

    private function defaultNightlyPrice(Space $space, ?SpaceRoom $room, array $nightDates): float
    {
        $prices = AvailabilityDay::query()
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereIn('date', $nightDates)
            ->where('status', 'available')
            ->whereNotNull('price')
            ->pluck('price');

        if ($prices->isEmpty()) {
            return 0.0;
        }

        return round((float) $prices->avg(), 2);
    }

    private function capacity(Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): int
    {
        if ($bedUnit) {
            return (int) ($bedUnit->bedType?->capacity ?: 1);
        }

        if ($room) {
            return (int) ($room->max_capacity ?: $room->beds->sum('total_capacity') ?: 1);
        }

        return (int) ($space->max_capacity ?: 1);
    }

    private function validateDates(string $checkIn, string $checkOut): void
    {
        $start = CarbonImmutable::parse($checkIn)->startOfDay();
        $end = CarbonImmutable::parse($checkOut)->startOfDay();

        if ($start->lt(today())) {
            throw ValidationException::withMessages([
                'check_in' => 'La fecha de ingreso no puede ser pasada.',
            ]);
        }

        if ($end->lte($start)) {
            throw ValidationException::withMessages([
                'check_out' => 'La fecha de salida debe ser posterior al ingreso.',
            ]);
        }
    }

    private function nightDates(string $checkIn, string $checkOut): array
    {
        $start = CarbonImmutable::parse($checkIn);
        $end = CarbonImmutable::parse($checkOut)->subDay();

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->all();
    }

    private function reservationCode(): string
    {
        do {
            $code = 'RSV-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::query()->withoutGlobalScope('company')->where('code', $code)->exists());

        return $code;
    }
}
