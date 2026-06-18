<?php

namespace App\Services\CheckIn;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\AvailabilityDay;
use App\Models\ExtraChargeCategory;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Stay;
use Carbon\CarbonImmutable;

class AccountStatementService
{
    public function createForStay(Stay $stay): AccountStatement
    {
        $statement = AccountStatement::query()->create([
            'company_id' => $stay->company_id,
            'stay_id' => $stay->id,
            'currency' => $stay->currency,
            'subtotal' => 0,
            'discount_total' => 0,
            'extra_charges_total' => 0,
            'payments_total' => 0,
            'balance' => 0,
            'status' => 'pending',
        ]);

        foreach ($this->lodgingNightItems($stay, $statement) as $item) {
            AccountStatementItem::query()->create($item);
        }

        return $this->recalculate($statement);
    }

    public function createForReservationGroup(ReservationGroup $group): AccountStatement
    {
        $statement = AccountStatement::query()->create([
            'company_id' => $group->company_id,
            'stay_id' => null,
            'reservation_id' => null,
            'reservation_group_id' => $group->id,
            'currency' => $group->currency,
            'subtotal' => 0,
            'discount_total' => 0,
            'extra_charges_total' => 0,
            'payments_total' => 0,
            'balance' => 0,
            'status' => 'pending',
        ]);

        $reservations = Reservation::query()
            ->withoutGlobalScope('company')
            ->with([
                'space',
                'room',
                'roomItems.room',
                'bedUnitItems.bedUnit.room',
            ])
            ->where('reservation_group_id', $group->id)
            ->get();

        foreach ($reservations as $reservation) {
            foreach ($this->reservationNightItems($reservation, $statement) as $item) {
                AccountStatementItem::query()->create($item);
            }
        }

        return $this->recalculate($statement);
    }

    public function recordReservationPayment(ReservationGroup $group, float $amount, string $description, ?string $currency = null): AccountStatementItem
    {
        $statement = $group->accountStatement ?: $this->createForReservationGroup($group);
        $statement = $this->recalculate($statement);
        $currency ??= $statement->currency ?: $group->currency;
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > (float) $statement->balance) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'El monto debe ser mayor a cero y no puede superar el saldo.',
            ])->errorBag('reservationPayment');
        }

        $item = AccountStatementItem::query()->create([
            'company_id' => $group->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => null,
            'reservation_id' => null,
            'reservation_group_id' => $group->id,
            'date' => now()->toDateString(),
            'type' => 'payment',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => -$amount,
            'total' => -$amount,
            'currency' => $currency,
            'source' => 'manual',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function addLodgingNightCharge(Stay $stay, string $date, ?float $unitPrice = null): AccountStatementItem
    {
        $statement = $stay->accountStatement ?: $this->createForStay($stay);
        $unitPrice ??= $this->unitPrice($stay);
        $existing = $statement->items()
            ->where('stay_id', $stay->id)
            ->where('type', 'lodging_night')
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->update([
                'description' => $this->lodgingDescription($stay, $date),
                'unit_price' => $unitPrice,
                'total' => $unitPrice,
                'currency' => $stay->currency,
                'status' => 'active',
            ]);

            $this->recalculate($statement);

            return $existing->refresh();
        }

        $item = AccountStatementItem::query()->create([
            'company_id' => $stay->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => $stay->id,
            'date' => $date,
            'type' => 'lodging_night',
            'description' => $this->lodgingDescription($stay, $date),
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total' => $unitPrice,
            'currency' => $stay->currency,
            'source' => 'check_in',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function cancelLodgingNightCharge(Stay $stay, string $date): void
    {
        $statement = $stay->accountStatement;

        if (! $statement) {
            return;
        }

        $statement->items()
            ->where('stay_id', $stay->id)
            ->where('type', 'lodging_night')
            ->whereDate('date', $date)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $this->recalculate($statement);
    }

    public function addReservationNightCharge(Reservation $reservation, string $date, ?float $unitPrice = null): AccountStatementItem
    {
        $reservation->loadMissing('reservationGroup.accountStatement');
        $group = $reservation->reservationGroup;
        $statement = $group->accountStatement ?: $this->createForReservationGroup($group);
        $unitPrice ??= (float) $reservation->price_per_person;
        $existing = $statement->items()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'lodging_night')
            ->whereDate('date', $date)
            ->first();

        if ($existing) {
            $existing->update([
                'description' => $this->reservationLodgingDescription($reservation, $date),
                'unit_price' => $unitPrice,
                'total' => $unitPrice,
                'currency' => $reservation->currency,
                'status' => 'active',
            ]);

            $this->recalculate($statement);

            return $existing->refresh();
        }

        $item = AccountStatementItem::query()->create([
            'company_id' => $reservation->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => null,
            'reservation_id' => $reservation->id,
            'reservation_group_id' => $reservation->reservation_group_id,
            'date' => $date,
            'type' => 'lodging_night',
            'description' => $this->reservationLodgingDescription($reservation, $date),
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total' => $unitPrice,
            'currency' => $reservation->currency,
            'source' => 'check_in',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function cancelReservationNightCharge(Reservation $reservation, string $date): void
    {
        $statement = $reservation->reservationGroup?->accountStatement;

        if (! $statement) {
            return;
        }

        $statement->items()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'lodging_night')
            ->whereDate('date', $date)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        $this->recalculate($statement);
    }

    public function addExtraCharge(Stay $stay, ExtraChargeCategory $category, array $data): AccountStatementItem
    {
        $statement = $stay->accountStatement ?: $this->createForStay($stay);
        $quantity = round((float) $data['quantity'], 2);
        $unitPrice = round((float) $data['unit_price'], 2);
        $detail = trim((string) ($data['detail'] ?? '')) ?: $category->name;

        $item = AccountStatementItem::query()->create([
            'company_id' => $stay->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => $stay->id,
            'extra_charge_category_id' => $category->id,
            'date' => $data['date'] ?? now()->toDateString(),
            'type' => 'extra',
            'description' => $category->name.' - '.$detail,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total' => round($quantity * $unitPrice, 2),
            'currency' => 'BOB',
            'source' => 'manual',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function recordPayment(Stay $stay, float $amount, string $description, ?string $currency = null): AccountStatementItem
    {
        $statement = $stay->accountStatement ?: $this->createForStay($stay);
        $statement = $this->recalculate($statement);
        $currency ??= $statement->currency ?: $stay->currency;
        $amount = round($amount, 2);

        if ($amount <= 0 || $amount > (float) $statement->balance) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'amount' => 'El monto debe ser mayor a cero y no puede superar el saldo.',
            ])->errorBag('stayPayment');
        }

        $item = AccountStatementItem::query()->create([
            'company_id' => $stay->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => $stay->id,
            'date' => now()->toDateString(),
            'type' => 'payment',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => -$amount,
            'total' => -$amount,
            'currency' => $currency,
            'source' => 'manual',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function recordAdjustment(Stay $stay, float $amount, string $description, ?string $currency = null): AccountStatementItem
    {
        $statement = $stay->accountStatement ?: $this->createForStay($stay);
        $currency ??= $statement->currency ?: $stay->currency;
        $amount = round($amount, 2);

        $item = AccountStatementItem::query()->create([
            'company_id' => $stay->company_id,
            'account_statement_id' => $statement->id,
            'stay_id' => $stay->id,
            'date' => now()->toDateString(),
            'type' => 'adjustment',
            'description' => $description,
            'quantity' => 1,
            'unit_price' => $amount,
            'total' => $amount,
            'currency' => $currency,
            'source' => 'system',
            'status' => 'active',
        ]);

        $this->recalculate($statement);

        return $item;
    }

    public function cancelExtraCharge(AccountStatementItem $item): void
    {
        if ($item->type !== 'extra' || $item->status !== 'active') {
            return;
        }

        $item->update(['status' => 'cancelled']);
        $this->recalculate($item->accountStatement);
    }

    public function updateNightlyRate(Stay $stay): void
    {
        $this->updateNightlyRateFrom($stay, '0001-01-01');
    }

    public function updateNightlyRateFrom(Stay $stay, string $fromDate, array $nightPrices = []): void
    {
        $statement = $stay->accountStatement;

        if (! $statement) {
            return;
        }

        $statement->items()
            ->where('stay_id', $stay->id)
            ->where('type', 'lodging_night')
            ->where('status', 'active')
            ->whereDate('date', '>=', $fromDate)
            ->each(function (AccountStatementItem $item) use ($stay, $nightPrices): void {
                $date = $item->date->toDateString();
                $unitPrice = array_key_exists($date, $nightPrices)
                    ? round((float) $nightPrices[$date], 2)
                    : $this->unitPrice($stay);

                $item->update([
                    'description' => $this->lodgingDescription($stay, $date),
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice,
                    'currency' => $stay->currency,
                ]);
            });

        $this->recalculate($statement);
    }

    public function updateReservationNightlyRateFrom(Reservation $reservation, string $fromDate, array $nightPrices = []): void
    {
        $statement = $reservation->reservationGroup?->accountStatement;

        if (! $statement) {
            return;
        }

        $statement->items()
            ->where('reservation_id', $reservation->id)
            ->where('type', 'lodging_night')
            ->where('status', 'active')
            ->whereDate('date', '>=', $fromDate)
            ->each(function (AccountStatementItem $item) use ($reservation, $nightPrices): void {
                $date = $item->date->toDateString();
                $unitPrice = array_key_exists($date, $nightPrices)
                    ? round((float) $nightPrices[$date], 2)
                    : (float) $reservation->price_per_person;

                $item->update([
                    'description' => $this->reservationLodgingDescription($reservation, $date),
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice,
                    'currency' => $reservation->currency,
                ]);
            });

        $this->recalculate($statement);
    }

    public function recalculate(AccountStatement $statement): AccountStatement
    {
        $items = AccountStatementItem::query()
            ->withoutGlobalScope('company')
            ->where('account_statement_id', $statement->id)
            ->where('status', 'active')
            ->get();
        $subtotal = $items->whereIn('type', ['lodging_night', 'breakfast', 'extra', 'adjustment'])->sum('total');
        $discountTotal = abs($items->where('type', 'discount')->sum('total'));
        $paymentsTotal = abs($items->where('type', 'payment')->sum('total'));
        $extraChargesTotal = $items->whereIn('type', ['breakfast', 'extra', 'adjustment'])->sum('total');
        $balance = $subtotal - $discountTotal - $paymentsTotal;

        $statement->update([
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'extra_charges_total' => $extraChargesTotal,
            'payments_total' => $paymentsTotal,
            'balance' => $balance,
            'status' => $balance <= 0 ? 'paid' : ($paymentsTotal > 0 ? 'partial' : 'pending'),
        ]);

        return $statement->refresh();
    }

    private function lodgingNightItems(Stay $stay, AccountStatement $statement): array
    {
        $start = CarbonImmutable::parse($stay->check_in_date);
        $unitPrice = $stay->currency === 'USD'
            ? (float) $stay->price_per_night_usd
            : (float) $stay->price_per_night_bob;
        $resourceLabel = $this->resourceLabel($stay);

        return collect(range(0, max((int) $stay->nights - 1, 0)))
            ->map(fn (int $offset): array => [
                'company_id' => $stay->company_id,
                'account_statement_id' => $statement->id,
                'stay_id' => $stay->id,
                'date' => $start->addDays($offset)->toDateString(),
                'type' => 'lodging_night',
                'description' => 'Hospedaje '.$resourceLabel.' - Noche '.$start->addDays($offset)->toDateString(),
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'total' => $unitPrice,
                'currency' => $stay->currency,
                'source' => 'check_in',
                'status' => 'active',
            ])
            ->all();
    }

    private function reservationNightItems(Reservation $reservation, AccountStatement $statement): array
    {
        if (($reservation->booking_type ?? 'normal') === 'package') {
            return $this->packageReservationItems($reservation, $statement);
        }

        $start = CarbonImmutable::parse($reservation->check_in);
        $unitPrice = (float) $reservation->price_per_person;
        $resourceLabel = $this->reservationResourceLabel($reservation);

        return collect(range(0, max((int) $reservation->nights - 1, 0)))
            ->map(fn (int $offset): array => [
                'company_id' => $reservation->company_id,
                'account_statement_id' => $statement->id,
                'stay_id' => null,
                'reservation_id' => $reservation->id,
                'reservation_group_id' => $reservation->reservation_group_id,
                'date' => $start->addDays($offset)->toDateString(),
                'type' => 'lodging_night',
                'description' => 'Reserva '.$resourceLabel.' - Noche '.$start->addDays($offset)->toDateString(),
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'total' => $unitPrice,
                'currency' => $reservation->currency,
                'source' => 'check_in',
                'status' => 'active',
            ])
            ->all();
    }

    private function packageReservationItems(Reservation $reservation, AccountStatement $statement): array
    {
        $start = CarbonImmutable::parse($reservation->check_in);
        $nights = max((int) $reservation->nights, 0);
        $includedNights = min($nights, max((int) data_get($reservation->package_snapshot, 'nights_included', 1), 1));
        $packageNightPrices = $this->distributedPackageNightPrices((float) $reservation->package_price, $includedNights);
        $extraNightPrices = $this->extraNightPrices($reservation, $includedNights, $nights);
        $packageName = trim((string) data_get($reservation->package_snapshot, 'name')) ?: 'Paquete';
        $resourceLabel = $this->reservationResourceLabel($reservation);

        $items = collect(range(0, max($nights - 1, 0)))
            ->map(function (int $offset) use ($reservation, $statement, $start, $includedNights, $packageNightPrices, $extraNightPrices, $packageName, $resourceLabel): array {
                $date = $start->addDays($offset)->toDateString();
                $isPackageNight = $offset < $includedNights;
                $unitPrice = $isPackageNight
                    ? $packageNightPrices[$offset]
                    : ($extraNightPrices[$date] ?? 0.0);

                return [
                    'company_id' => $reservation->company_id,
                    'account_statement_id' => $statement->id,
                    'stay_id' => null,
                    'reservation_id' => $reservation->id,
                    'reservation_group_id' => $reservation->reservation_group_id,
                    'date' => $date,
                    'type' => 'lodging_night',
                    'description' => ($isPackageNight ? 'Detalle paquete '.$packageName : 'Hospedaje extra '.$resourceLabel).' - Noche '.$date,
                    'quantity' => 1,
                    'unit_price' => $unitPrice,
                    'total' => $unitPrice,
                    'currency' => $reservation->currency,
                    'source' => 'check_in',
                    'status' => 'active',
                ];
            })
            ->all();

        $extraPeople = max((int) ($reservation->extra_people ?? 0), 0);
        $extraPeopleTotal = round((float) ($reservation->extra_people_total ?? 0), 2);

        if ($extraPeople > 0 && $extraPeopleTotal > 0 && $nights > 0) {
            $unitPrice = round($extraPeopleTotal / ($extraPeople * $nights), 2);

            foreach (range(0, $nights - 1) as $offset) {
                $date = $start->addDays($offset)->toDateString();

                $items[] = [
                    'company_id' => $reservation->company_id,
                    'account_statement_id' => $statement->id,
                    'stay_id' => null,
                    'reservation_id' => $reservation->id,
                    'reservation_group_id' => $reservation->reservation_group_id,
                    'date' => $date,
                    'type' => 'extra',
                    'description' => 'Personas extra paquete '.$packageName.' - Noche '.$date,
                    'quantity' => $extraPeople,
                    'unit_price' => $unitPrice,
                    'total' => round($extraPeople * $unitPrice, 2),
                    'currency' => $reservation->currency,
                    'source' => 'check_in',
                    'status' => 'active',
                ];
            }
        }

        return $items;
    }

    private function distributedPackageNightPrices(float $packagePrice, int $includedNights): array
    {
        if ($includedNights <= 0) {
            return [];
        }

        $base = round($packagePrice / $includedNights, 2);
        $prices = array_fill(0, $includedNights, $base);
        $prices[$includedNights - 1] = round($packagePrice - ($base * ($includedNights - 1)), 2);

        return $prices;
    }

    private function extraNightPrices(Reservation $reservation, int $includedNights, int $nights): array
    {
        if ($nights <= $includedNights) {
            return [];
        }

        $start = CarbonImmutable::parse($reservation->check_in);
        $extraDates = collect(range($includedNights, $nights - 1))
            ->map(fn (int $offset): string => $start->addDays($offset)->toDateString())
            ->all();
        $fallbackPrice = round((float) ($reservation->package_extra_nights_total ?? 0) / max(count($extraDates), 1), 2);

        $prices = AvailabilityDay::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $reservation->company_id)
            ->where('space_id', $reservation->space_id)
            ->whereNull('space_room_id')
            ->whereNull('room_bed_unit_id')
            ->whereIn('date', $extraDates)
            ->pluck('price', 'date')
            ->map(fn ($price): float => round((float) $price, 2))
            ->all();

        return collect($extraDates)
            ->mapWithKeys(fn (string $date): array => [$date => $prices[$date] ?? $fallbackPrice])
            ->all();
    }

    private function unitPrice(Stay $stay): float
    {
        return $stay->currency === 'USD'
            ? (float) $stay->price_per_night_usd
            : (float) $stay->price_per_night_bob;
    }

    private function lodgingDescription(Stay $stay, string $date): string
    {
        return 'Hospedaje '.$this->resourceLabel($stay).' - Noche '.$date;
    }

    private function reservationLodgingDescription(Reservation $reservation, string $date): string
    {
        return 'Reserva '.$this->reservationResourceLabel($reservation).' - Noche '.$date;
    }

    private function resourceLabel(Stay $stay): string
    {
        $stay->loadMissing(['space', 'room', 'bedUnit']);

        return collect([
            $stay->space?->name ?: $stay->space?->title,
            $stay->room?->name ?: $stay->room?->title,
            $stay->bedUnit?->label,
        ])->filter()->implode(' / ') ?: 'Hospedaje';
    }

    private function reservationResourceLabel(Reservation $reservation): string
    {
        $reservation->loadMissing([
            'space',
            'room',
            'roomItems.room',
            'bedUnitItems.bedUnit.room',
        ]);

        if ($reservation->bedUnitItems->isNotEmpty()) {
            return $reservation->bedUnitItems
                ->map(fn ($item): string => collect([
                    $reservation->space?->name ?: $reservation->space?->title,
                    $item->bedUnit?->room?->name ?: $item->bedUnit?->room?->title,
                    $item->bedUnit?->label,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        if ($reservation->roomItems->isNotEmpty()) {
            return $reservation->roomItems
                ->map(fn ($item): string => collect([
                    $reservation->space?->name ?: $reservation->space?->title,
                    $item->room?->name ?: $item->room?->title,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        return collect([
            $reservation->space?->name ?: $reservation->space?->title,
            $reservation->room?->name ?: $reservation->room?->title,
        ])->filter()->implode(' / ') ?: 'Reserva';
    }
}
