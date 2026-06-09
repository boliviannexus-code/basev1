<?php

namespace App\Services\CheckIn;

use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\StayGuest;
use App\Models\User;
use App\Services\Availability\AvailabilityStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StayRoomMoveService
{
    public function __construct(
        private readonly AvailabilityStatusService $availability,
        private readonly AccountStatementService $accounts,
    ) {}

    public function move(Stay $stay, array $data, ?User $user = null): Stay
    {
        return DB::transaction(function () use ($stay, $data, $user): Stay {
            $stay->refresh()->loadMissing(['checkInGroup', 'accountStatement.items', 'guests']);

            if ($stay->status !== 'occupied') {
                throw ValidationException::withMessages([
                    'status' => 'Solo se puede cambiar habitacion de una estancia ocupada.',
                ]);
            }

            $today = CarbonImmutable::today();
            $checkIn = CarbonImmutable::parse($stay->check_in_date);
            $checkOut = CarbonImmutable::parse($stay->check_out_date);

            if ($today->gte($checkOut)) {
                throw ValidationException::withMessages([
                    'check_out_date' => 'No se puede cambiar habitacion porque no quedan noches pendientes.',
                ]);
            }

            $moveStart = $today->lte($checkIn) ? $checkIn : $today;
            $sameDayMove = $today->isSameDay($checkIn);
            $nightPrices = $this->nightPricesForRange($data, $moveStart, $checkOut);
            [$space, $room, $bedUnit] = $this->validateTarget($stay, $data, $moveStart, $checkOut);
            $this->ensureResourceType((string) $data['resource_type'], $space, $room, $bedUnit);
            $this->ensureDifferentResource($stay, $space, $room, $bedUnit);
            $this->ensureCapacity((int) $stay->people_count, $space, $room, $bedUnit);

            return $sameDayMove
                ? $this->moveEntireStay($stay, $data, $nightPrices, $space, $room, $bedUnit, $checkIn, $checkOut, $user)
                : $this->splitFutureStay($stay, $data, $nightPrices, $space, $room, $bedUnit, $moveStart, $checkOut, $user);
        });
    }

    private function moveEntireStay(
        Stay $stay,
        array $data,
        array $nightPrices,
        Space $space,
        ?SpaceRoom $room,
        ?RoomBedUnit $bedUnit,
        CarbonImmutable $checkIn,
        CarbonImmutable $checkOut,
        ?User $user,
    ): Stay {
        $dates = $this->nightDates($checkIn, $checkOut);

        $this->availability->releaseOccupiedRange($stay, $dates);
        $defaultPrice = $this->defaultNightPrice($data, $nightPrices, $stay);
        $stay->update([
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'price_per_night_bob' => $defaultPrice,
            'price_per_night_usd' => null,
            'exchange_rate' => $stay->exchange_rate ?: 1,
            'currency' => 'BOB',
            'notes' => $data['notes'] ?? $stay->notes,
        ]);
        $this->availability->markOccupiedRange($stay->refresh(), $dates, $user);
        $this->accounts->updateNightlyRateFrom($stay, $checkIn->toDateString(), $nightPrices);
        $this->refreshGroupDates($stay);

        return $stay->refresh()->load(['accountStatement.items', 'space', 'room', 'bedUnit']);
    }

    private function splitFutureStay(
        Stay $stay,
        array $data,
        array $nightPrices,
        Space $space,
        ?SpaceRoom $room,
        ?RoomBedUnit $bedUnit,
        CarbonImmutable $moveStart,
        CarbonImmutable $originalCheckOut,
        ?User $user,
    ): Stay {
        $originalCheckIn = CarbonImmutable::parse($stay->check_in_date);
        $futureDates = $this->nightDates($moveStart, $originalCheckOut);

        $this->availability->releaseOccupiedRange($stay, $futureDates);

        foreach ($futureDates as $date) {
            $this->accounts->cancelLodgingNightCharge($stay, $date);
        }

        $stay->update([
            'check_out_date' => $moveStart->toDateString(),
            'nights' => max($originalCheckIn->diffInDays($moveStart), 1),
            'status' => 'checked_out',
        ]);

        $this->accounts->recalculate($stay->accountStatement);
        $balanceToTransfer = round((float) $stay->accountStatement->refresh()->balance, 2);

        $defaultPrice = $this->defaultNightPrice($data, $nightPrices, $stay);
        $newStay = Stay::query()->create([
            'company_id' => $stay->company_id,
            'check_in_group_id' => $stay->check_in_group_id,
            'holder_guest_id' => $stay->holder_guest_id,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'people_count' => $stay->people_count,
            'check_in_date' => $moveStart->toDateString(),
            'check_out_date' => $originalCheckOut->toDateString(),
            'nights' => $moveStart->diffInDays($originalCheckOut),
            'price_per_night_bob' => $defaultPrice,
            'price_per_night_usd' => null,
            'exchange_rate' => $stay->exchange_rate ?: 1,
            'currency' => 'BOB',
            'breakfast_included' => $stay->breakfast_included,
            'status' => 'occupied',
            'notes' => $data['notes'] ?? $stay->notes,
        ]);

        $this->copyGuests($stay, $newStay);
        $this->availability->markOccupiedRange($newStay, $futureDates, $user);
        $this->accounts->createForStay($newStay);
        $this->accounts->updateNightlyRateFrom($newStay, $moveStart->toDateString(), $nightPrices);
        $this->transferBalance($stay, $newStay, $balanceToTransfer);
        $this->refreshGroupDates($newStay);

        return $newStay->refresh()->load(['accountStatement.items', 'space', 'room', 'bedUnit']);
    }

    private function validateTarget(Stay $stay, array $data, CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        [$space, $room, $bedUnit] = $this->availability->validateRangeAvailable(
            companyId: (int) $stay->company_id,
            data: [
                'space_id' => $data['space_id'],
                'space_room_id' => $data['space_room_id'] ?? null,
                'room_bed_unit_id' => $data['room_bed_unit_id'] ?? null,
                'check_in_date' => $checkIn->toDateString(),
                'check_out_date' => $checkOut->toDateString(),
                'confirm_reserved_conversion' => false,
            ],
        );

        return [$space, $room, $bedUnit];
    }

    private function nightPricesForRange(array $data, CarbonImmutable $moveStart, CarbonImmutable $checkOut): array
    {
        $allowedDates = $this->nightDates($moveStart, $checkOut);
        $prices = $data['night_prices'] ?? [];

        foreach ($prices as $date => $price) {
            if (! in_array((string) $date, $allowedDates, true)) {
                throw ValidationException::withMessages([
                    "night_prices.{$date}" => 'Solo se pueden modificar precios de noches futuras del movimiento.',
                ]);
            }
        }

        return collect($allowedDates)
            ->mapWithKeys(fn (string $date): array => [
                $date => array_key_exists($date, $prices)
                    ? round((float) $prices[$date], 2)
                    : round((float) $data['price_per_night_bob'], 2),
            ])
            ->all();
    }

    private function defaultNightPrice(array $data, array $nightPrices, Stay $stay): float
    {
        if ($nightPrices !== []) {
            return (float) reset($nightPrices);
        }

        return round((float) ($data['price_per_night_bob'] ?? $stay->price_per_night_bob), 2);
    }

    private function ensureDifferentResource(Stay $stay, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        if ((int) $stay->space_id === (int) $space->id
            && (int) ($stay->space_room_id ?? 0) === (int) ($room?->id ?? 0)
            && (int) ($stay->room_bed_unit_id ?? 0) === (int) ($bedUnit?->id ?? 0)) {
            throw ValidationException::withMessages([
                'space_id' => 'Selecciona una habitacion diferente a la actual.',
            ]);
        }
    }

    private function ensureResourceType(string $resourceType, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        if ($resourceType === 'private_space' && ($room || $bedUnit)) {
            throw ValidationException::withMessages([
                'resource_type' => 'El recurso seleccionado no es un alojamiento privado.',
            ]);
        }

        if ($resourceType === 'shared_room') {
            if (! $room || $bedUnit || ! in_array($room->sale_mode, ['full_room', 'flexible'], true)) {
                throw ValidationException::withMessages([
                    'resource_type' => 'La habitacion seleccionada no permite venta como habitacion completa.',
                ]);
            }
        }

        if ($resourceType === 'shared_bed_unit') {
            if (! $room || ! $bedUnit || ! in_array($room->sale_mode, ['bed_unit', 'flexible'], true)) {
                throw ValidationException::withMessages([
                    'resource_type' => 'La cama seleccionada no esta disponible para venta por cama.',
                ]);
            }
        }
    }

    private function ensureCapacity(int $peopleCount, Space $space, ?SpaceRoom $room, ?RoomBedUnit $bedUnit): void
    {
        $capacity = match (true) {
            $bedUnit !== null => 1,
            $room !== null => max(
                (int) ($room->max_capacity ?: 0)
                    ?: (int) $room->beds()->sum('total_capacity')
                    ?: (int) $room->bedUnits()->where('status', 'active')->count(),
                1,
            ),
            default => max((int) ($space->max_capacity ?: 1), 1),
        };

        if ($peopleCount > $capacity) {
            throw ValidationException::withMessages([
                'space_id' => "La capacidad maxima de este recurso es {$capacity}.",
            ]);
        }
    }

    private function transferBalance(Stay $fromStay, Stay $toStay, float $balance): void
    {
        if (abs($balance) < 0.01) {
            return;
        }

        $this->accounts->recordAdjustment($fromStay, -$balance, 'Traspaso de saldo por cambio de habitacion');
        $this->accounts->recordAdjustment($toStay, $balance, 'Saldo trasladado por cambio de habitacion');
    }

    private function copyGuests(Stay $fromStay, Stay $toStay): void
    {
        $fromStay->loadMissing('guests');

        foreach ($fromStay->guests as $guest) {
            StayGuest::query()->create([
                'company_id' => $toStay->company_id,
                'stay_id' => $toStay->id,
                'guest_id' => $guest->id,
                'is_holder' => (bool) $guest->pivot?->is_holder,
            ]);
        }
    }

    private function refreshGroupDates(Stay $stay): void
    {
        $group = $stay->checkInGroup()->first();
        $maxCheckOut = $group?->stays()->where('status', 'occupied')->max('check_out_date')
            ?: $group?->stays()->max('check_out_date');

        $group?->update([
            'status' => 'checked_in',
            'check_out_date' => $maxCheckOut ?: $group->check_out_date,
        ]);
    }

    private function nightDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        return collect(range(0, max($checkIn->diffInDays($checkOut) - 1, 0)))
            ->map(fn (int $offset): string => $checkIn->addDays($offset)->toDateString())
            ->all();
    }
}
