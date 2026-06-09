<?php

namespace App\Services\CheckIn;

use App\Models\CheckInGroup;
use App\Models\Reservation;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Models\StayGuest;
use App\Models\User;
use App\Services\Availability\AvailabilityStatusService;
use App\Services\ExtraChargeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public function __construct(
        private readonly GuestService $guests,
        private readonly CurrencyConversionService $currency,
        private readonly AvailabilityStatusService $availability,
        private readonly AccountStatementService $accounts,
        private readonly ExtraChargeService $extraCharges,
    ) {}

    public function checkIn(int $companyId, array $data, ?User $user = null): CheckInGroup
    {
        return DB::transaction(function () use ($companyId, $data, $user): CheckInGroup {
            $holder = $this->guests->findOrCreateHolder($companyId, $data['main_guest'], $user?->id);

            $group = CheckInGroup::query()->create([
                'company_id' => $companyId,
                'code' => CheckInGroup::nextCode($companyId),
                'main_guest_id' => $holder->id,
                'reservation_channel_id' => $data['reservation_channel_id'] ?? null,
                'total_people' => $data['total_people'],
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'status' => 'checked_in',
                'notes' => $data['notes'] ?? null,
                'created_by' => $user?->id,
            ]);

            $transferredReservationIds = [];

            foreach ($data['stays'] as $stayData) {
                $stayData = $this->currency->normalizeStayPrices($stayData, $companyId);
                [$space, $room, $bedUnit] = $this->availability->validateRangeAvailable(
                    companyId: $companyId,
                    data: [
                        'space_id' => $stayData['space_id'],
                        'space_room_id' => $stayData['space_room_id'] ?? null,
                        'room_bed_unit_id' => $stayData['room_bed_unit_id'] ?? null,
                        'check_in_date' => $data['check_in_date'],
                        'check_out_date' => $data['check_out_date'],
                        'confirm_reserved_conversion' => (bool) ($data['confirm_reserved_conversion'] ?? false),
                    ],
                );
                $this->ensureCapacity($stayData['people_count'], $space, $room, $bedUnit);

                $stay = Stay::query()->create([
                    'company_id' => $companyId,
                    'check_in_group_id' => $group->id,
                    'holder_guest_id' => $holder->id,
                    'space_id' => $space->id,
                    'space_room_id' => $room?->id,
                    'room_bed_unit_id' => $bedUnit?->id,
                    'people_count' => $stayData['people_count'],
                    'check_in_date' => $data['check_in_date'],
                    'check_out_date' => $data['check_out_date'],
                    'nights' => CarbonImmutable::parse($data['check_in_date'])->diffInDays(CarbonImmutable::parse($data['check_out_date'])),
                    'price_per_night_bob' => $stayData['price_per_night_bob'],
                    'price_per_night_usd' => $stayData['price_per_night_usd'],
                    'exchange_rate' => $stayData['exchange_rate'],
                    'currency' => $stayData['currency'],
                    'breakfast_included' => (bool) ($stayData['breakfast_included'] ?? false),
                    'status' => 'occupied',
                    'notes' => $stayData['notes'] ?? null,
                ]);

                StayGuest::query()->create([
                    'company_id' => $companyId,
                    'stay_id' => $stay->id,
                    'guest_id' => $holder->id,
                    'is_holder' => true,
                ]);

                foreach ($stayData['guests'] ?? [] as $guestData) {
                    $guest = $this->guests->createStayGuest($companyId, $guestData, $user?->id);

                    StayGuest::query()->create([
                        'company_id' => $companyId,
                        'stay_id' => $stay->id,
                        'guest_id' => $guest->id,
                        'is_holder' => false,
                    ]);
                }

                $this->availability->markRangeOccupied($companyId, $stay, $user);
                $this->accounts->createForStay($stay);

                if ($reservation = $this->matchingReservation($companyId, $stay, $data)) {
                    if (! in_array($reservation->id, $transferredReservationIds, true)) {
                        $this->extraCharges->transferReservationChargesToStay($reservation, $stay);
                        $transferredReservationIds[] = $reservation->id;
                    }
                }
            }

            return $group->load(['mainGuest', 'stays.guests', 'stays.accountStatement']);
        });
    }

    private function matchingReservation(int $companyId, Stay $stay, array $data): ?Reservation
    {
        $query = Reservation::query()
            ->withoutGlobalScope('company')
            ->with('extraCharges.category')
            ->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->whereDate('check_in', $data['check_in_date'])
            ->whereDate('check_out', $data['check_out_date'])
            ->where('space_id', $stay->space_id);

        if ($stay->room_bed_unit_id) {
            $query->whereHas('bedUnitItems', fn ($itemQuery) => $itemQuery->where('room_bed_unit_id', $stay->room_bed_unit_id));
        } elseif ($stay->space_room_id) {
            $query->where(function ($roomQuery) use ($stay): void {
                $roomQuery
                    ->where('space_room_id', $stay->space_room_id)
                    ->orWhereHas('roomItems', fn ($itemQuery) => $itemQuery->where('space_room_id', $stay->space_room_id));
            });
        } else {
            $query->whereNull('space_room_id')
                ->whereDoesntHave('roomItems')
                ->whereDoesntHave('bedUnitItems');
        }

        return $query->first();
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
                'stays' => "La cantidad de personas supera la capacidad maxima de {$capacity}.",
            ]);
        }
    }
}
