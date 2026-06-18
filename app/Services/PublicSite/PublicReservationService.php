<?php

namespace App\Services\PublicSite;

use App\Models\AvailabilityDay;
use App\Models\AccommodationPackage;
use App\Models\OccupancyBlock;
use App\Models\Reservation;
use App\Models\ReservationBedUnit;
use App\Models\ReservationChannel;
use App\Models\ReservationGroup;
use App\Models\ReservationRoom;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use App\Services\Reservations\ReservationManagementService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PublicReservationService
{
    public function __construct(
        private readonly ReservationManagementService $reservationManagement,
        private readonly AccountStatementService $accountStatements,
    ) {}

    public function quote(array $data): array
    {
        [$space, $rooms, $bedUnits] = $this->resolveResource($data);
        $this->reservationManagement->expireOverduePending((int) $space->company_id);

        $nightDates = $this->nightDates($data['check_in'], $data['check_out']);
        if (filled($data['package_id'] ?? null)) {
            return $this->quotePackage($space, $data, $nightDates);
        }

        $guests = (int) $data['guests'];
        $isShared = $space->spaceMode?->slug === 'compartido';
        $capacity = $isShared
            ? ($bedUnits->isNotEmpty()
                ? $bedUnits->sum(fn (RoomBedUnit $unit): int => (int) ($unit->bedType?->capacity ?: 1))
                : $rooms->sum(fn (SpaceRoom $room): int => $this->roomCapacity($room)))
            : (int) $space->max_capacity;

        if ($capacity < $guests) {
            throw ValidationException::withMessages([
                'guests' => 'La capacidad disponible no cubre la cantidad de huespedes.',
            ]);
        }

        $roomItems = collect();
        $bedUnitItems = collect();
        $availabilityDays = collect();

        if ($isShared && $bedUnits->isNotEmpty()) {
            $bedUnitItems = $bedUnits->map(function (RoomBedUnit $unit) use ($space, $nightDates, $data): array {
                $room = $unit->room;
                $bedUnitAvailabilityDays = $this->availabilityDaysForBedUnit($space, $unit, $nightDates);

                if ($bedUnitAvailabilityDays->count() !== count($nightDates)) {
                    throw ValidationException::withMessages([
                        'room_bed_unit_ids' => 'Una o mas camas seleccionadas no tienen precio o estan cerradas.',
                    ]);
                }

                if ($this->hasActiveBlock($space, $room, $data['check_in'], $data['check_out'])
                    || $this->hasActiveBlockForBedUnit($space, $unit, $data['check_in'], $data['check_out'])) {
                    throw ValidationException::withMessages([
                        'room_bed_unit_ids' => 'Una o mas camas seleccionadas ya no estan disponibles.',
                    ]);
                }

                if ($this->hasRoomLevelReservation($space, $room, $data['check_in'], $data['check_out'])) {
                    throw ValidationException::withMessages([
                        'room_bed_unit_ids' => 'La habitacion de una cama seleccionada ya esta reservada completa.',
                    ]);
                }

                $subtotal = round((float) $bedUnitAvailabilityDays->sum(fn (AvailabilityDay $day): float => (float) $day->price), 2);

                return [
                    'bed_unit' => $unit,
                    'room' => $room,
                    'capacity' => (int) ($unit->bedType?->capacity ?: 1),
                    'price_per_night' => round($subtotal / count($nightDates), 2),
                    'subtotal_amount' => $subtotal,
                    'nightly_prices' => $this->nightlyPricesPayload($bedUnitAvailabilityDays),
                ];
            })->values();
        } elseif ($isShared) {
            $roomItems = $rooms->map(function (SpaceRoom $room) use ($space, $nightDates, $data): array {
                $roomAvailabilityDays = $this->availabilityDays($space, $room, $nightDates);

                if ($roomAvailabilityDays->count() !== count($nightDates)) {
                    throw ValidationException::withMessages([
                        'space_room_ids' => 'Una o mas habitaciones seleccionadas no tienen precio o estan cerradas.',
                    ]);
                }

                if ($this->hasActiveBlock($space, $room, $data['check_in'], $data['check_out'])
                    || $this->hasActiveBlockForAnyBedUnit($space, $room, $data['check_in'], $data['check_out'])) {
                    throw ValidationException::withMessages([
                        'space_room_ids' => 'Una o mas habitaciones seleccionadas ya no estan disponibles.',
                    ]);
                }

                if ($this->hasBedUnitReservation($space, $room, $data['check_in'], $data['check_out'])) {
                    throw ValidationException::withMessages([
                        'space_room_ids' => 'Una o mas habitaciones tienen camas reservadas y no pueden venderse completas.',
                    ]);
                }

                $subtotal = round((float) $roomAvailabilityDays->sum(fn (AvailabilityDay $day): float => (float) $day->price), 2);

                return [
                    'room' => $room,
                    'capacity' => $this->roomCapacity($room),
                    'price_per_night' => round($subtotal / count($nightDates), 2),
                    'subtotal_amount' => $subtotal,
                    'nightly_prices' => $this->nightlyPricesPayload($roomAvailabilityDays),
                ];
            })->values();
        } else {
            $availabilityDays = $this->availabilityDays($space, null, $nightDates);
        }

        if (! $isShared && $availabilityDays->count() !== count($nightDates)) {
            throw ValidationException::withMessages([
                'check_in' => 'Las fechas seleccionadas no tienen precio o estan cerradas.',
            ]);
        }

        if (! $isShared && $this->hasActiveBlock($space, null, $data['check_in'], $data['check_out'])) {
            throw ValidationException::withMessages([
                'check_in' => 'El alojamiento ya no esta disponible para esas fechas.',
            ]);
        }

        $nights = count($nightDates);
        $subtotal = $isShared
            ? round((float) ($bedUnitItems->isNotEmpty() ? $bedUnitItems->sum('subtotal_amount') : $roomItems->sum('subtotal_amount')), 2)
            : round((float) $availabilityDays->sum(fn (AvailabilityDay $day): float => (float) $day->price), 2);
        $total = $subtotal;
        $advance = $this->advanceAmount($total, $space);
        $balance = round($total - $advance, 2);
        $averageNightlyPrice = round($subtotal / $nights, 2);

        return [
            'space' => $space,
            'room' => $rooms->count() === 1 ? $rooms->first() : null,
            'rooms' => $rooms,
            'room_items' => $roomItems,
            'bed_units' => $bedUnits,
            'bed_unit_items' => $bedUnitItems,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'nights' => $nights,
            'guests' => $guests,
            'capacity' => $capacity,
            'price_per_person' => $averageNightlyPrice,
            'price_per_night' => $averageNightlyPrice,
            'subtotal_amount' => $subtotal,
            'total_amount' => $total,
            'advance_amount' => $advance,
            'balance_amount' => $balance,
            'nightly_prices' => $this->nightlyPricesPayload($availabilityDays),
        ];
    }

    public function create(array $data): Reservation
    {
        $user = $this->resolveUser($data);

        return DB::transaction(function () use ($data, $user): Reservation {
            $quote = $this->quote($data);
            $space = $quote['space'];
            $rooms = $quote['rooms'];
            $bedUnits = $quote['bed_units'];
            $room = $quote['room'];
            $lastNight = CarbonImmutable::parse($quote['check_out'])->subDay()->toDateString();
            $group = $this->createReservationGroup($quote, $data, $user);

            $reservation = Reservation::query()->create([
                'company_id' => $space->company_id,
                'reservation_group_id' => $group->id,
                'user_id' => $user->id,
                'space_id' => $space->id,
                'space_room_id' => $room?->id,
                'reservation_channel_id' => $group->reservation_channel_id,
                'code' => $this->code(),
                'package_id' => ($quote['package'] ?? null)?->id,
                'booking_type' => $quote['booking_type'] ?? 'normal',
                'package_snapshot' => $quote['package_snapshot'] ?? null,
                'package_price' => $quote['package_price'] ?? null,
                'included_people' => $quote['included_people'] ?? null,
                'extra_people' => $quote['extra_people'] ?? null,
                'extra_people_total' => $quote['extra_people_total'] ?? null,
                'package_extra_nights' => $quote['package_extra_nights'] ?? null,
                'package_extra_nights_total' => $quote['package_extra_nights_total'] ?? null,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'] ?? null,
                'guest_country' => $data['guest_country'] ?? null,
                'guest_document' => $data['guest_document'] ?? null,
                'check_in' => $quote['check_in'],
                'check_out' => $quote['check_out'],
                'nights' => $quote['nights'],
                'guests' => $quote['guests'],
                'price_per_person' => $quote['price_per_person'],
                'subtotal_amount' => $quote['subtotal_amount'],
                'total_amount' => $quote['total_amount'],
                'advance_amount' => $quote['advance_amount'],
                'deposit_amount' => $quote['advance_amount'],
                'balance_amount' => $quote['balance_amount'],
                'currency' => 'BOB',
                'status' => 'pending_payment',
                'payment_status' => 'pending',
                'payment_method' => 'qr',
                'hold_expires_at' => now()->addMinutes((int) config('reservations.temporary_hold_minutes', 60)),
                'guest_notes' => $data['guest_notes'] ?? null,
            ]);

            if ($reservation->shouldBlockAvailability() && $rooms->isEmpty()) {
                $block = OccupancyBlock::query()->create([
                    'company_id' => $space->company_id,
                    'space_id' => $space->id,
                    'space_room_id' => $room?->id,
                    'type' => 'unavailable',
                    'status' => 'active',
                    'title' => 'Solicitud '.$reservation->code.' pendiente de pago',
                    'description' => 'Bloqueo temporal generado desde el flujo publico de reservas.',
                    'start_date' => $quote['check_in'],
                    'end_date' => $lastNight,
                    'created_by' => null,
                ]);

                $reservation->update(['occupancy_block_id' => $block->id]);
            }

            if ($bedUnits->isNotEmpty()) {
                $firstBlockId = null;

                foreach ($quote['bed_unit_items'] as $item) {
                    $block = null;

                    if ($reservation->shouldBlockAvailability()) {
                        $block = OccupancyBlock::query()->create([
                            'company_id' => $space->company_id,
                            'space_id' => $space->id,
                            'space_room_id' => $item['room']->id,
                            'room_bed_unit_id' => $item['bed_unit']->id,
                            'type' => 'unavailable',
                            'status' => 'active',
                            'title' => 'Solicitud '.$reservation->code.' pendiente de pago',
                            'description' => 'Bloqueo temporal de cama generado desde el flujo publico de reservas.',
                            'start_date' => $quote['check_in'],
                            'end_date' => $lastNight,
                            'created_by' => null,
                        ]);
                    }

                    $firstBlockId ??= $block?->id;

                    ReservationBedUnit::query()->create([
                        'reservation_id' => $reservation->id,
                        'room_bed_unit_id' => $item['bed_unit']->id,
                        'occupancy_block_id' => $block?->id,
                        'guest_name' => $reservation->guest_name,
                        'price_per_night' => $item['price_per_night'],
                        'subtotal_amount' => $item['subtotal_amount'],
                    ]);
                }

                if ($firstBlockId !== null) {
                    $reservation->update(['occupancy_block_id' => $firstBlockId]);
                }
            } elseif ($rooms->isNotEmpty()) {
                $firstBlockId = null;

                foreach ($quote['room_items'] as $item) {
                    $block = null;

                    if ($reservation->shouldBlockAvailability()) {
                        $block = OccupancyBlock::query()->create([
                            'company_id' => $space->company_id,
                            'space_id' => $space->id,
                            'space_room_id' => $item['room']->id,
                            'type' => 'unavailable',
                            'status' => 'active',
                            'title' => 'Solicitud '.$reservation->code.' pendiente de pago',
                            'description' => 'Bloqueo temporal generado desde el flujo publico de reservas.',
                            'start_date' => $quote['check_in'],
                            'end_date' => $lastNight,
                            'created_by' => null,
                        ]);
                    }

                    $firstBlockId ??= $block?->id;

                    ReservationRoom::query()->create([
                        'reservation_id' => $reservation->id,
                        'space_room_id' => $item['room']->id,
                        'occupancy_block_id' => $block?->id,
                        'capacity' => $item['capacity'],
                        'price_per_night' => $item['price_per_night'],
                        'subtotal_amount' => $item['subtotal_amount'],
                    ]);
                }

                if ($firstBlockId !== null) {
                    $reservation->update(['occupancy_block_id' => $firstBlockId]);
                }
            }

            $this->accountStatements->createForReservationGroup($group->refresh()->load([
                'reservations.space',
                'reservations.room',
                'reservations.roomItems.room',
                'reservations.bedUnitItems.bedUnit.room',
            ]));

            return $reservation->load([
                'reservationGroup.accountStatement.items',
                'space.location',
                'room',
                'rooms',
                'roomItems.occupancyBlock',
                'bedUnits',
                'bedUnitItems.occupancyBlock',
                'occupancyBlock',
            ]);
        });
    }

    private function createReservationGroup(array $quote, array $data, User $user): ReservationGroup
    {
        $space = $quote['space'];
        $reservationChannelId = $this->publicReservationChannelId((int) $space->company_id);

        return ReservationGroup::query()->create([
            'company_id' => $space->company_id,
            'reservation_channel_id' => $reservationChannelId,
            'guest_name' => $data['guest_name'],
            'guest_email' => $data['guest_email'],
            'guest_phone' => $data['guest_phone'] ?? null,
            'guest_document' => $data['guest_document'] ?? null,
            'check_in' => $quote['check_in'],
            'check_out' => $quote['check_out'],
            'nights' => $quote['nights'],
            'guests' => $quote['guests'],
            'subtotal_amount' => $quote['subtotal_amount'],
            'total_amount' => $quote['total_amount'],
            'advance_amount' => $quote['advance_amount'],
            'balance_amount' => $quote['balance_amount'],
            'currency' => 'BOB',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => 'qr',
            'payment_reference' => null,
            'notes' => $data['guest_notes'] ?? 'Reserva creada desde el portal publico.',
            'created_by' => $user->company_id ? $user->id : null,
        ]);
    }

    private function publicReservationChannelId(int $companyId): int
    {
        $channel = ReservationChannel::query()
            ->withoutGlobalScope('company')
            ->withTrashed()
            ->firstOrNew([
                'company_id' => $companyId,
                'slug' => 'pagina-web',
            ]);

        $channel->fill([
            'name' => 'Página web',
            'type' => 'direct',
            'commission_percent' => null,
            'is_active' => true,
            'is_protected' => true,
            'sort_order' => 0,
        ]);

        if ($channel->trashed()) {
            $channel->restore();
        }

        $channel->save();

        return (int) $channel->id;
    }

    private function resolveUser(array $data): User
    {
        if ($user = Auth::user()) {
            return $user;
        }

        $mode = $data['account_mode'] ?? 'register';
        $existing = User::query()->where('email', $data['guest_email'])->first();

        if ($mode === 'login') {
            if (! $existing || ! $existing->is_active || ! Hash::check((string) $data['password'], $existing->password)) {
                throw ValidationException::withMessages([
                    'guest_email' => 'No pudimos iniciar sesion con ese correo y contrasena.',
                ]);
            }

            Auth::login($existing);

            return $existing;
        }

        if ($existing) {
            throw ValidationException::withMessages([
                'guest_email' => 'Ya existe una cuenta con este correo. Elige iniciar sesion para continuar.',
            ]);
        }

        $user = User::query()->create([
            'company_id' => null,
            'name' => $data['guest_name'],
            'email' => $data['guest_email'],
            'password' => $data['password'],
            'is_active' => true,
        ]);

        Auth::login($user);

        return $user;
    }

    private function resolveResource(array $data): array
    {
        $space = Space::query()
            ->withoutGlobalScope('company')
            ->with(['company', 'spaceMode', 'location', 'photos', 'rooms.beds', 'rooms.bedUnits.bedType'])
            ->publicBookable()
            ->whereKey($data['space_id'])
            ->first();

        if (! $space) {
            throw ValidationException::withMessages([
                'space_id' => 'El alojamiento no esta disponible para reservas publicas.',
            ]);
        }

        $isShared = $space->spaceMode?->slug === 'compartido';
        $roomIds = $this->roomIds($data);
        $bedUnitIds = $this->bedUnitIds($data);

        if (! $isShared && (filled($data['space_room_id'] ?? null) || $roomIds !== [] || $bedUnitIds !== [])) {
            throw ValidationException::withMessages([
                'space_room_id' => 'Un alojamiento privado no permite seleccionar habitacion.',
            ]);
        }

        if (! $isShared) {
            return [$space, collect(), collect()];
        }

        if ($roomIds !== [] && $bedUnitIds !== []) {
            throw ValidationException::withMessages([
                'room_bed_unit_ids' => 'Selecciona habitaciones completas o camas individuales, no ambas a la vez.',
            ]);
        }

        if ($roomIds === [] && $bedUnitIds === []) {
            throw ValidationException::withMessages([
                'space_room_ids' => 'Selecciona al menos una habitacion o cama para continuar.',
            ]);
        }

        if ($bedUnitIds !== []) {
            $bedUnits = RoomBedUnit::query()
                ->withoutGlobalScope('company')
                ->with([
                    'room' => fn ($query) => $query->withoutGlobalScope('company')->with('beds'),
                    'bedType',
                ])
                ->where('company_id', $space->company_id)
                ->where('status', 'active')
                ->whereIn('id', $bedUnitIds)
                ->get()
                ->sortBy(fn (RoomBedUnit $unit): int => array_search((int) $unit->id, $bedUnitIds, true))
                ->values();

            if ($bedUnits->count() !== count($bedUnitIds) || $bedUnits->contains(fn (RoomBedUnit $unit): bool => (int) $unit->room?->space_id !== (int) $space->id)) {
                throw ValidationException::withMessages([
                    'room_bed_unit_ids' => 'Una o mas camas seleccionadas no pertenecen al alojamiento.',
                ]);
            }

            if ($bedUnits->contains(fn (RoomBedUnit $unit): bool => ! in_array($unit->room?->sale_mode, ['bed_unit', 'flexible'], true))) {
                throw ValidationException::withMessages([
                    'room_bed_unit_ids' => 'Una o mas habitaciones no estan configuradas para vender camas individuales.',
                ]);
            }

            return [$space, $bedUnits->pluck('room')->unique('id')->values(), $bedUnits];
        }

        $rooms = SpaceRoom::query()
            ->withoutGlobalScope('company')
            ->with('beds')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('status', 'active')
            ->whereIn('id', $roomIds)
            ->get()
            ->sortBy(fn (SpaceRoom $room): int => array_search((int) $room->id, $roomIds, true))
            ->values();

        if ($rooms->count() !== count($roomIds)) {
            throw ValidationException::withMessages([
                'space_room_ids' => 'Una o mas habitaciones seleccionadas no pertenecen al alojamiento.',
            ]);
        }

        if ($rooms->contains(fn (SpaceRoom $room): bool => ! in_array($room->sale_mode, ['full_room', 'flexible'], true))) {
            throw ValidationException::withMessages([
                'space_room_ids' => 'Una o mas habitaciones no estan configuradas para venta completa.',
            ]);
        }

        return [$space, $rooms, collect()];
    }

    private function quotePackage(Space $space, array $data, array $nightDates): array
    {
        if ($space->spaceMode?->slug !== 'privado') {
            throw ValidationException::withMessages([
                'package_id' => 'Los paquetes todo incluido solo estan disponibles para espacios privados.',
            ]);
        }

        $package = AccommodationPackage::query()
            ->withoutGlobalScope('company')
            ->with(['services' => fn ($query) => $query
                ->where('package_services.is_active', true)
                ->orderByPivot('sort_order')
                ->orderBy('package_services.name')])
            ->where('company_id', $space->company_id)
            ->where('is_active', true)
            ->whereKey($data['package_id'])
            ->whereHas('spaces', fn (Builder $query): Builder => $query->where('spaces.id', $space->id))
            ->first();

        if (! $package) {
            throw ValidationException::withMessages([
                'package_id' => 'El paquete seleccionado no esta disponible para este alojamiento.',
            ]);
        }

        if (count($nightDates) === 0) {
            throw ValidationException::withMessages([
                'check_in' => 'Selecciona fecha de ingreso y salida para reservar el paquete.',
            ]);
        }

        $guests = (int) $data['guests'];
        $capacity = (int) $space->max_capacity;

        if ($capacity < $guests) {
            throw ValidationException::withMessages([
                'guests' => 'La capacidad del espacio no cubre la cantidad de huespedes.',
            ]);
        }

        $extraPeople = max(0, $guests - (int) $package->included_people);
        $extraPersonPrice = (float) ($package->extra_person_price ?? 0);

        if ($package->max_people !== null && $guests > (int) $package->max_people && $extraPersonPrice <= 0) {
            throw ValidationException::withMessages([
                'guests' => 'El paquete permite maximo '.$package->max_people.' persona'.((int) $package->max_people === 1 ? '' : 's').'.',
            ]);
        }

        if ($extraPeople > 0 && $extraPersonPrice <= 0) {
            throw ValidationException::withMessages([
                'guests' => 'El paquete no tiene precio configurado para personas extra.',
            ]);
        }

        $availabilityDays = $this->availabilityDays($space, null, $nightDates);

        if ($availabilityDays->count() !== count($nightDates)) {
            throw ValidationException::withMessages([
                'check_in' => 'Las fechas seleccionadas no tienen disponibilidad para este paquete.',
            ]);
        }

        if ($this->hasActiveBlock($space, null, $data['check_in'], $data['check_out'])) {
            throw ValidationException::withMessages([
                'check_in' => 'El alojamiento ya no esta disponible para esas fechas.',
            ]);
        }

        $packagePrice = round((float) $package->price, 2);
        $extraPeopleDetails = $this->packageExtraPeopleDetails($data['check_in'], $nightDates, $extraPeople, $extraPersonPrice);
        $extraPeopleTotal = round((float) $extraPeopleDetails->sum('total'), 2);
        $extraNights = max(0, count($nightDates) - (int) $package->nights_included);
        $extraNightsTotal = round((float) $availabilityDays
            ->sortBy(fn (AvailabilityDay $day): string => $day->date->toDateString())
            ->skip((int) $package->nights_included)
            ->sum(fn (AvailabilityDay $day): float => (float) $day->price), 2);
        $total = round($packagePrice + $extraPeopleTotal + $extraNightsTotal, 2);
        $advance = $this->advanceAmount($total, $space);
        $balance = round($total - $advance, 2);
        $nights = count($nightDates);
        $averagePrice = round($total / $nights, 2);

        return [
            'space' => $space,
            'room' => null,
            'rooms' => collect(),
            'room_items' => collect(),
            'bed_units' => collect(),
            'bed_unit_items' => collect(),
            'package' => $package,
            'booking_type' => 'package',
            'package_snapshot' => $this->packageSnapshot($package),
            'package_price' => $packagePrice,
            'included_people' => (int) $package->included_people,
            'extra_people' => $extraPeople,
            'extra_person_price' => $extraPersonPrice,
            'extra_people_total' => $extraPeopleTotal,
            'extra_people_details' => $extraPeopleDetails,
            'package_nights_included' => (int) $package->nights_included,
            'package_extra_nights' => $extraNights,
            'package_extra_nights_total' => $extraNightsTotal,
            'check_in' => $data['check_in'],
            'check_out' => $data['check_out'],
            'nights' => $nights,
            'guests' => $guests,
            'capacity' => $capacity,
            'price_per_person' => $averagePrice,
            'price_per_night' => $averagePrice,
            'subtotal_amount' => round($packagePrice + $extraNightsTotal, 2),
            'total_amount' => $total,
            'advance_amount' => $advance,
            'balance_amount' => $balance,
            'nightly_prices' => $this->nightlyPricesPayload($availabilityDays),
        ];
    }

    private function packageExtraPeopleDetails(string $checkIn, array $nightDates, int $extraPeople, float $extraPersonPrice): Collection
    {
        if ($extraPeople <= 0 || $extraPersonPrice <= 0) {
            return collect();
        }

        $start = CarbonImmutable::parse($checkIn);

        return collect($nightDates)
            ->values()
            ->map(fn (string $date, int $index): array => [
                'date' => $date,
                'night' => $index + 1,
                'label' => 'Personas extra - Noche '.$start->addDays($index)->toDateString(),
                'quantity' => $extraPeople,
                'unit_price' => round($extraPersonPrice, 2),
                'total' => round($extraPeople * $extraPersonPrice, 2),
            ]);
    }

    private function packageSnapshot(AccommodationPackage $package): array
    {
        return [
            'id' => $package->id,
            'name' => $package->name,
            'slug' => $package->slug,
            'short_description' => $package->short_description,
            'badges' => $package->badges ?? [],
            'commercial_description' => $package->commercial_description,
            'conditions' => $package->conditions,
            'price' => (float) $package->price,
            'currency' => $package->currency,
            'price_display_text' => $package->price_display_text,
            'included_people' => (int) $package->included_people,
            'max_people' => $package->max_people === null ? null : (int) $package->max_people,
            'extra_person_price' => $package->extra_person_price === null ? null : (float) $package->extra_person_price,
            'nights_included' => (int) $package->nights_included,
            'main_image' => $package->main_image,
            'video_url' => $package->video_url,
            'services' => $package->services
                ->map(fn ($service): array => [
                    'id' => $service->id,
                    'name' => $service->pivot->custom_name ?: $service->name,
                    'description' => $service->pivot->custom_description ?: $service->description,
                    'type' => $service->type,
                    'inclusion_type' => $service->pivot->inclusion_type,
                    'additional_price' => $service->pivot->additional_price === null ? null : (float) $service->pivot->additional_price,
                ])
                ->values()
                ->all(),
        ];
    }

    private function availabilityDays(Space $space, ?SpaceRoom $room, array $nightDates)
    {
        return AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when(
                $room,
                fn (Builder $query): Builder => $query->where('space_room_id', $room->id)->whereNull('room_bed_unit_id'),
                fn (Builder $query): Builder => $query->whereNull('space_room_id')->whereNull('room_bed_unit_id'),
            )
            ->whereIn('date', $nightDates)
            ->where('status', 'available')
            ->where('is_public_online', true)
            ->whereNotNull('price')
            ->get()
            ->keyBy(fn (AvailabilityDay $day): string => $day->date->toDateString());
    }

    private function availabilityDaysForBedUnit(Space $space, RoomBedUnit $unit, array $nightDates): Collection
    {
        return AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('space_room_id', $unit->space_room_id)
            ->where(function (Builder $query) use ($unit): void {
                $query
                    ->where('room_bed_unit_id', $unit->id)
                    ->orWhereNull('room_bed_unit_id');
            })
            ->whereIn('date', $nightDates)
            ->where('status', 'available')
            ->where('is_public_online', true)
            ->whereNotNull('price')
            ->get()
            ->groupBy(fn (AvailabilityDay $day): string => $day->date->toDateString())
            ->map(fn (Collection $days): AvailabilityDay => $days
                ->sortByDesc(fn (AvailabilityDay $day): int => $day->room_bed_unit_id === null ? 0 : 1)
                ->first())
            ->filter()
            ->sortKeys();
    }

    private function hasActiveBlock(Space $space, ?SpaceRoom $room, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->when($room, fn (Builder $query): Builder => $query->where('space_room_id', $room->id), fn (Builder $query): Builder => $query->whereNull('space_room_id'))
            ->whereNull('room_bed_unit_id')
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationRoom.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->exists();
    }

    private function hasActiveBlockForAnyBedUnit(Space $space, SpaceRoom $room, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('space_room_id', $room->id)
            ->whereNotNull('room_bed_unit_id')
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationBedUnit.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->exists();
    }

    private function hasActiveBlockForBedUnit(Space $space, RoomBedUnit $bedUnit, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return OccupancyBlock::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('room_bed_unit_id', $bedUnit->id)
            ->where('status', 'active')
            ->where(function (Builder $query): void {
                $query
                    ->whereDoesntHave('reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation))
                    ->whereDoesntHave('reservationBedUnit.reservation', fn (Builder $reservation): Builder => $this->expiredPendingHold($reservation));
            })
            ->whereDate('start_date', '<=', $lastNight)
            ->whereDate('end_date', '>=', $checkIn)
            ->exists();
    }

    private function hasRoomLevelReservation(Space $space, SpaceRoom $room, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return Reservation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $space->company_id)
            ->where('space_id', $space->id)
            ->where('space_room_id', $room->id)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->whereDate('check_in', '<=', $lastNight)
            ->whereDate('check_out', '>', $checkIn)
            ->exists();
    }

    private function hasBedUnitReservation(Space $space, SpaceRoom $room, string $checkIn, string $checkOut): bool
    {
        $lastNight = CarbonImmutable::parse($checkOut)->subDay()->toDateString();

        return ReservationBedUnit::query()
            ->whereHas('bedUnit', fn (Builder $query): Builder => $query
                ->withoutGlobalScope('company')
                ->where('company_id', $space->company_id)
                ->where('space_room_id', $room->id))
            ->whereHas('reservation', fn (Builder $query): Builder => $query
                ->withoutGlobalScope('company')
                ->whereIn('status', Reservation::BLOCKING_STATUSES)
                ->whereDate('check_in', '<=', $lastNight)
                ->whereDate('check_out', '>', $checkIn))
            ->exists();
    }

    private function expiredPendingHold(Builder $query): Builder
    {
        return $query
            ->withoutGlobalScope('company')
            ->where('status', 'pending_payment')
            ->where('payment_status', 'pending')
            ->whereNotNull('hold_expires_at')
            ->where('hold_expires_at', '<=', now());
    }

    private function nightDates(string $checkIn, string $checkOut): array
    {
        $start = CarbonImmutable::parse($checkIn);
        $end = CarbonImmutable::parse($checkOut)->subDay();

        return collect(CarbonPeriod::create($start, $end))
            ->map(fn ($date): string => $date->toDateString())
            ->all();
    }

    private function roomCapacity(SpaceRoom $room): int
    {
        $bedsCapacity = (int) $room->beds->sum('total_capacity');

        return (int) ($room->max_capacity ?: $bedsCapacity);
    }

    private function roomIds(array $data): array
    {
        $ids = $data['space_room_ids'] ?? null;

        if (($ids === null || $ids === []) && filled($data['space_room_id'] ?? null)) {
            $ids = [$data['space_room_id']];
        }

        return collect($ids ?? [])
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function bedUnitIds(array $data): array
    {
        return collect($data['room_bed_unit_ids'] ?? [])
            ->push($data['room_bed_unit_id'] ?? null)
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function nightlyPricesPayload(Collection $availabilityDays): array
    {
        return $availabilityDays
            ->sortBy(fn (AvailabilityDay $day): string => $day->date->toDateString())
            ->map(fn (AvailabilityDay $day): array => [
                'date' => $day->date->toDateString(),
                'price' => (float) $day->price,
            ])
            ->values()
            ->all();
    }

    private function advanceAmount(float $total, Space $space): float
    {
        $companyPercentage = $space->company?->reservation_advance_percentage;
        $type = (string) config('reservations.advance.type', 'percentage');

        $amount = match ($type) {
            'fixed' => (float) config('reservations.advance.fixed_amount', 0),
            default => $total * (((float) ($companyPercentage ?? config('reservations.advance.percentage', 50))) / 100),
        };

        return round(min(max($amount, 0), $total), 2);
    }

    private function code(): string
    {
        do {
            $code = 'RSV-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
        } while (Reservation::query()->withoutGlobalScope('company')->where('code', $code)->exists());

        return $code;
    }
}
