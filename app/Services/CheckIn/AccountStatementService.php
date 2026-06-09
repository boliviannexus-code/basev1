<?php

namespace App\Services\CheckIn;

use App\Models\AccountStatement;
use App\Models\AccountStatementItem;
use App\Models\ExtraChargeCategory;
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

    public function recalculate(AccountStatement $statement): AccountStatement
    {
        $items = $statement->items()->where('status', 'active')->get();
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

    private function resourceLabel(Stay $stay): string
    {
        $stay->loadMissing(['space', 'room', 'bedUnit']);

        return collect([
            $stay->space?->name ?: $stay->space?->title,
            $stay->room?->name ?: $stay->room?->title,
            $stay->bedUnit?->label,
        ])->filter()->implode(' / ') ?: 'Hospedaje';
    }
}
