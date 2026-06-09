<?php

namespace App\Services;

use App\Models\SpaceCashRegister;
use App\Models\SpaceCashUserSequence;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SpaceCashRegisterService
{
    public function openForUser(User $user, float $openingAmount): SpaceCashRegister
    {
        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'opening_amount' => 'Tu usuario no esta vinculado a una empresa.',
            ]);
        }

        if ($this->currentForUser($user)) {
            throw ValidationException::withMessages([
                'opening_amount' => 'Ya tienes una caja de espacios abierta.',
            ]);
        }

        return SpaceCashRegister::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'opening_amount' => $openingAmount,
            'opened_at' => now(),
            'status' => 'open',
        ]);
    }

    public function closeForUser(User $user, float $closingAmount): SpaceCashRegister
    {
        $cashRegister = $this->currentForUser($user);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'closing_amount' => 'No tienes una caja de espacios abierta para cerrar.',
            ])->errorBag('spaceCashClose');
        }

        $cashRegister->update([
            'closing_amount' => $closingAmount,
            'closed_at' => now(),
            'status' => 'closed',
        ]);

        return $cashRegister->refresh();
    }

    public function currentForUser(User $user): ?SpaceCashRegister
    {
        return SpaceCashRegister::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->where('status', 'open')
            ->with(['company', 'user'])
            ->first();
    }

    public function nextReceiptNumber(User $user): string
    {
        if (! $user->company_id) {
            throw ValidationException::withMessages([
                'amount' => 'Tu usuario no esta vinculado a una empresa.',
            ]);
        }

        $sequence = SpaceCashUserSequence::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            $sequence = SpaceCashUserSequence::query()->create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'receipt_prefix' => 'ESP-'.$user->id,
                'receipt_next_number' => 1,
                'receipt_digits' => 6,
            ]);

            $sequence = SpaceCashUserSequence::query()
                ->whereKey($sequence->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $number = (int) $sequence->receipt_next_number;
        $receipt = $sequence->receipt_prefix.'-'.str_pad((string) $number, (int) $sequence->receipt_digits, '0', STR_PAD_LEFT);
        $sequence->increment('receipt_next_number');

        return $receipt;
    }

    public function cashSummary(SpaceCashRegister $cashRegister): array
    {
        $cashRegister->loadMissing([
            'expenses',
            'lodgingPayments.paymentMethod',
            'lodgingPayments.stay.holderGuest',
            'company',
            'user',
        ]);

        $lodgingPayments = $cashRegister->lodgingPayments->where('status', 'active');
        $expenses = $cashRegister->expenses;
        $cashMethod = fn (string $name): bool => mb_strtolower($name) === 'efectivo';

        $lodgingCashTotal = (float) $lodgingPayments
            ->filter(fn ($payment): bool => $cashMethod((string) $payment->paymentMethod?->name))
            ->sum('amount_bob');
        $expensesTotal = (float) $expenses->sum('amount');
        $lodgingTotal = (float) $lodgingPayments->sum('amount_bob');

        return [
            'opening' => (float) $cashRegister->opening_amount,
            'lodging_total' => $lodgingTotal,
            'income_total' => $lodgingTotal,
            'expenses' => $expensesTotal,
            'available' => (float) $cashRegister->opening_amount + $lodgingCashTotal - $expensesTotal,
            'payments' => $this->paymentRows($lodgingPayments),
            'lodging_payments' => $lodgingPayments->sortByDesc('created_at')->values(),
            'expense_details' => $expenses->sortByDesc('spent_at')->values(),
        ];
    }

    private function paymentRows(Collection $lodgingPayments): array
    {
        return $lodgingPayments
            ->map(fn ($payment): array => [
                'name' => $payment->paymentMethod?->name ?: 'Pago',
                'total' => (float) $payment->amount_bob,
            ])
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'payments_count' => $rows->count(),
                'total' => $rows->sum('total'),
            ])
            ->values()
            ->all();
    }
}
