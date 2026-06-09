<?php

namespace App\Services\CheckIn;

use App\Models\CheckInGroup;
use App\Models\Guest;
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

class CheckInUpdateService
{
    public function __construct(
        private readonly GuestService $guests,
        private readonly CurrencyConversionService $currency,
        private readonly AvailabilityStatusService $availability,
        private readonly AccountStatementService $accounts,
    ) {}

    public function updateGroup(CheckInGroup $group, array $data, ?User $user = null): CheckInGroup
    {
        return DB::transaction(function () use ($group, $data, $user): CheckInGroup {
            $group->update([
                'total_people' => $data['total_people'] ?? $group->total_people,
            ]);

            foreach ($data['stays'] ?? [] as $stayId => $stayData) {
                $stay = $group->stays()->whereKey($stayId)->firstOrFail();
                $this->updateStay($stay, $stayData, $user);
            }

            $group->update([
                'check_out_date' => $group->stays()->max('check_out_date') ?: $group->check_out_date,
                'total_people' => $group->stays()->sum('people_count'),
            ]);

            return $group->refresh()->load(['stays.accountStatement', 'mainGuest']);
        });
    }

    public function updateStay(Stay $stay, array $data, ?User $user = null): Stay
    {
        return DB::transaction(function () use ($stay, $data, $user): Stay {
            $stay->refresh();

            if ($stay->status !== 'occupied') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede modificar una estancia cancelada o con check-out realizado.',
                ]);
            }

            $data = $this->currency->normalizeStayPrices([
                ...$stay->only(['currency', 'price_per_night_bob', 'price_per_night_usd', 'exchange_rate']),
                ...$data,
            ], (int) $stay->company_id);

            $newCheckOut = CarbonImmutable::parse($data['check_out_date'] ?? $stay->check_out_date->toDateString());
            $checkIn = CarbonImmutable::parse($stay->check_in_date);

            if ($newCheckOut->lte($checkIn)) {
                throw ValidationException::withMessages([
                    'check_out_date' => 'La fecha de salida debe ser posterior a la fecha de ingreso.',
                ]);
            }

            $this->ensureCapacity((int) ($data['people_count'] ?? $stay->people_count), $stay);

            $oldNights = $this->nightDates(
                CarbonImmutable::parse($stay->check_in_date),
                CarbonImmutable::parse($stay->check_out_date),
            );
            $newNights = $this->nightDates($checkIn, $newCheckOut);
            $toAdd = array_values(array_diff($newNights, $oldNights));
            $toCancel = array_values(array_diff($oldNights, $newNights));

            $stay->loadMissing(['space', 'room', 'bedUnit']);
            $this->availability->validateAvailableRange($stay, $toAdd);

            $stay->update([
                'people_count' => $data['people_count'] ?? $stay->people_count,
                'check_out_date' => $newCheckOut->toDateString(),
                'nights' => count($newNights),
                'price_per_night_bob' => $data['price_per_night_bob'],
                'price_per_night_usd' => $data['price_per_night_usd'],
                'exchange_rate' => $data['exchange_rate'],
                'currency' => $data['currency'],
                'breakfast_included' => (bool) ($data['breakfast_included'] ?? $stay->breakfast_included),
                'notes' => $data['notes'] ?? $stay->notes,
            ]);

            $this->availability->markOccupiedRange($stay, $toAdd, $user);
            $this->availability->releaseOccupiedRange($stay, $toCancel);

            foreach ($toAdd as $date) {
                $this->accounts->addLodgingNightCharge($stay, $date, $data['night_prices'][$date] ?? null);
            }

            foreach ($toCancel as $date) {
                $this->accounts->cancelLodgingNightCharge($stay, $date);
            }

            $this->accounts->updateNightlyRateFrom($stay, now()->toDateString(), $data['night_prices'] ?? []);
            $this->syncStayGuests($stay, $data['guests'] ?? [], $user);

            return $stay->refresh()->load(['accountStatement.items', 'holderGuest', 'guests.birthCountry']);
        });
    }

    public function updateHolder(Stay $stay, int $guestId): Stay
    {
        return DB::transaction(function () use ($stay, $guestId): Stay {
            if ($stay->status !== 'occupied') {
                throw ValidationException::withMessages([
                    'status' => 'No se puede modificar una estancia cancelada o con check-out realizado.',
                ]);
            }

            $guest = Guest::query()
                ->where('company_id', $stay->company_id)
                ->whereKey($guestId)
                ->firstOrFail();

            $stay->update(['holder_guest_id' => $guest->id]);
            StayGuest::query()
                ->where('stay_id', $stay->id)
                ->update(['is_holder' => false]);

            $stay->guests()->syncWithoutDetaching([
                $guest->id => ['company_id' => $stay->company_id, 'is_holder' => true],
            ]);

            return $stay->refresh()->load('holderGuest');
        });
    }

    private function nightDates(CarbonImmutable $checkIn, CarbonImmutable $checkOut): array
    {
        return collect(range(0, max($checkIn->diffInDays($checkOut) - 1, 0)))
            ->map(fn (int $offset): string => $checkIn->addDays($offset)->toDateString())
            ->all();
    }

    private function ensureCapacity(int $peopleCount, Stay $stay): void
    {
        $stay->loadMissing(['space', 'room', 'bedUnit']);
        $capacity = match (true) {
            $stay->bedUnit instanceof RoomBedUnit => 1,
            $stay->room instanceof SpaceRoom => max(
                (int) ($stay->room->max_capacity ?: 0)
                    ?: (int) $stay->room->beds()->sum('total_capacity')
                    ?: (int) $stay->room->bedUnits()->where('status', 'active')->count(),
                1,
            ),
            $stay->space instanceof Space => max((int) ($stay->space->max_capacity ?: 1), 1),
            default => 1,
        };

        if ($peopleCount > $capacity) {
            throw ValidationException::withMessages([
                'people_count' => "La cantidad de personas supera la capacidad maxima de {$capacity}.",
            ]);
        }
    }

    private function syncStayGuests(Stay $stay, array $guestRows, ?User $user = null): void
    {
        $holderId = (int) $stay->holder_guest_id;
        $keptGuestIds = [$holderId];
        $existingAdditionalIds = StayGuest::query()
            ->where('stay_id', $stay->id)
            ->where('is_holder', false)
            ->pluck('guest_id');
        $existingAdditional = Guest::query()
            ->where('company_id', $stay->company_id)
            ->whereIn('id', $existingAdditionalIds)
            ->get()
            ->keyBy('id');

        foreach ($guestRows as $guestData) {
            $guestId = (int) ($guestData['id'] ?? 0);
            $guest = $guestId > 0 ? $existingAdditional->get($guestId) : null;
            $guest ??= $guestId > 0
                ? Guest::query()
                    ->where('company_id', $stay->company_id)
                    ->whereKey($guestId)
                    ->first()
                : null;

            if ($guest instanceof Guest) {
                $guest->update([
                    'document_type' => $guestData['document_type'] ?? $guest->document_type,
                    'document_number' => $guestData['document_number'] ?? null,
                    'first_name' => $guestData['first_name'],
                    'last_name' => $guestData['last_name'],
                    'birth_date' => $guestData['birth_date'],
                    'birth_country_id' => $guestData['birth_country_id'],
                ]);
            } else {
                $guest = $this->guests->createStayGuest((int) $stay->company_id, $guestData, $user?->id);
            }

            $keptGuestIds[] = $guest->id;
            StayGuest::query()->updateOrCreate([
                'stay_id' => $stay->id,
                'guest_id' => $guest->id,
            ], [
                'company_id' => $stay->company_id,
                'is_holder' => false,
            ]);
        }

        $removedGuests = $existingAdditional->reject(fn (Guest $guest): bool => in_array((int) $guest->id, $keptGuestIds, true));

        if ($removedGuests->isNotEmpty()) {
            StayGuest::query()
                ->where('stay_id', $stay->id)
                ->whereIn('guest_id', $removedGuests->pluck('id')->all())
                ->delete();

            $removedGuests->each(function (Guest $guest): void {
                $isStillLinked = StayGuest::query()->where('guest_id', $guest->id)->exists()
                    || Stay::query()->where('holder_guest_id', $guest->id)->exists()
                    || CheckInGroup::query()->where('main_guest_id', $guest->id)->exists();

                if (! $isStillLinked) {
                    $guest->delete();
                }
            });
        }
    }
}
