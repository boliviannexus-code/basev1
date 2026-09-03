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

    public function companyHasOpenRegister(int $companyId): bool
    {
        return SpaceCashRegister::query()
            ->where('company_id', $companyId)
            ->where('status', 'open')
            ->exists();
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
            'expenses.category',
            'expenses.paymentMethod',
            'incomes.category',
            'incomes.paymentMethod',
            'lodgingPayments.paymentMethod',
            'lodgingPayments.stay.holderGuest',
            'reservationPayments.paymentMethod',
            'reservationPayments.reservationGroup',
            'company',
            'user',
        ]);

        $lodgingPayments = $cashRegister->lodgingPayments->where('status', 'active');
        $reservationPayments = $cashRegister->reservationPayments->where('status', 'active');
        $incomes = $cashRegister->incomes;
        $expenses = $cashRegister->expenses;
        $cashMethod = fn (string $name): bool => mb_strtolower($name) === 'efectivo';

        $directCashTotal = (float) $incomes
            ->filter(fn ($income): bool => $cashMethod((string) $income->paymentMethod?->name))
            ->sum('amount');
        $lodgingCashTotal = (float) $lodgingPayments
            ->filter(fn ($payment): bool => $cashMethod((string) $payment->paymentMethod?->name))
            ->sum('amount_bob');
        $reservationCashTotal = (float) $reservationPayments
            ->filter(fn ($payment): bool => $cashMethod((string) $payment->paymentMethod?->name))
            ->sum('amount_bob');
        $cashExpensesTotal = (float) $expenses
            ->filter(fn ($expense): bool => $cashMethod((string) ($expense->paymentMethod?->name ?? 'Efectivo')))
            ->sum('amount');
        $expensesTotal = (float) $expenses->sum('amount');
        $directTotal = (float) $incomes->sum('amount');
        $lodgingTotal = (float) $lodgingPayments->sum('amount_bob');
        $reservationTotal = (float) $reservationPayments->sum('amount_bob');
        $incomeTotal = $directTotal + $lodgingTotal + $reservationTotal;

        return [
            'opening' => (float) $cashRegister->opening_amount,
            'direct_total' => $directTotal,
            'lodging_total' => $lodgingTotal,
            'reservation_total' => $reservationTotal,
            'income_total' => $incomeTotal,
            'expenses' => $expensesTotal,
            'available' => (float) $cashRegister->opening_amount + $directCashTotal + $lodgingCashTotal + $reservationCashTotal - $cashExpensesTotal,
            'payments' => $this->paymentRows($lodgingPayments->concat($reservationPayments), $incomes),
            'expense_payments' => $this->expenseRows($expenses),
            'method_balances' => $this->methodBalances(
                (float) $cashRegister->opening_amount,
                $lodgingPayments->concat($reservationPayments),
                $incomes,
                $expenses,
            ),
            'direct_incomes' => $incomes->sortByDesc('received_at')->values(),
            'lodging_payments' => $lodgingPayments->sortByDesc('created_at')->values(),
            'reservation_payments' => $reservationPayments->sortByDesc('created_at')->values(),
            'expense_details' => $expenses->sortByDesc('spent_at')->values(),
        ];
    }

    private function paymentRows(Collection $lodgingPayments, Collection $incomes): array
    {
        return $lodgingPayments
            ->toBase()
            ->map(fn ($payment): array => [
                'name' => $payment->paymentMethod?->name ?: 'Pago',
                'total' => (float) $payment->amount_bob,
            ])
            ->merge($incomes->toBase()->map(fn ($income): array => [
                'name' => $income->paymentMethod?->name ?: 'Pago',
                'total' => (float) $income->amount,
            ]))
            ->groupBy('name')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'payments_count' => $rows->count(),
                'total' => $rows->sum('total'),
            ])
            ->values()
            ->all();
    }

    private function expenseRows(Collection $expenses): array
    {
        return $expenses
            ->toBase()
            ->map(fn ($expense): array => [
                'name' => $expense->paymentMethod?->name ?: 'Efectivo',
                'total' => (float) $expense->amount,
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

    private function methodBalances(float $openingAmount, Collection $lodgingPayments, Collection $incomes, Collection $expenses): array
    {
        $incomeRows = collect($this->paymentRows($lodgingPayments, $incomes))->keyBy('name');
        $expenseRows = collect($this->expenseRows($expenses))->keyBy('name');
        $names = $incomeRows->keys()->merge($expenseRows->keys())->push('Efectivo')->unique()->sort()->values();

        return $names
            ->map(function (string $name) use ($openingAmount, $incomeRows, $expenseRows): array {
                $opening = mb_strtolower($name) === 'efectivo' ? $openingAmount : 0.0;
                $income = (float) ($incomeRows->get($name)['total'] ?? 0);
                $expense = (float) ($expenseRows->get($name)['total'] ?? 0);

                return [
                    'name' => $name,
                    'opening' => $opening,
                    'income' => $income,
                    'expense' => $expense,
                    'balance' => $opening + $income - $expense,
                ];
            })
            ->values()
            ->all();
    }
}
