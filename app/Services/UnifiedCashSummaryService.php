<?php

namespace App\Services;

use App\Models\PaymentMethod;
use Illuminate\Support\Collection;

class UnifiedCashSummaryService
{
    public function combine(array $space, array $pos, ?int $paymentMethodId, int $companyId): array
    {
        $name = $paymentMethodId ? PaymentMethod::query()->where('company_id', $companyId)->whereKey($paymentMethodId)->value('name') : null;
        $filter = fn (array $rows): Collection => collect($rows)->when($paymentMethodId, fn (Collection $items) => $items->where('name', $name));
        $payments = $filter($pos['payments'])->concat($space['payments'])->groupBy('name')->map(fn (Collection $rows, string $method): array => [
            'name' => $method, 'payments_count' => $rows->sum('payments_count'), 'total' => $rows->sum('total'),
        ])->values()->all();
        $expenses = $filter($pos['expense_payments'])->concat($space['expense_payments'])->groupBy('name')->map(fn (Collection $rows, string $method): array => [
            'name' => $method, 'payments_count' => $rows->sum('payments_count'), 'total' => $rows->sum('total'),
        ])->values()->all();
        $includeOpening = ! $paymentMethodId || mb_strtolower((string) $name) === 'efectivo';
        $opening = $includeOpening ? (float) $pos['opening'] + (float) $space['opening'] : 0.0;
        $methods = collect($payments)->keyBy('name');
        $outflows = collect($expenses)->keyBy('name');
        $balances = $methods->keys()->merge($outflows->keys())->when($includeOpening, fn (Collection $names) => $names->push('Efectivo'))->unique()->sort()->values()->map(function (string $method) use ($opening, $methods, $outflows): array {
            $base = mb_strtolower($method) === 'efectivo' ? $opening : 0.0;
            $income = (float) ($methods->get($method)['total'] ?? 0);
            $expense = (float) ($outflows->get($method)['total'] ?? 0);
            return ['name' => $method, 'opening' => $base, 'income' => $income, 'expense' => $expense, 'balance' => $base + $income - $expense];
        })->all();
        $posIncome = $filter($pos['payments'])->sum('total');
        $posExpense = $filter($pos['expense_payments'])->sum('total');
        $cashIncome = collect($payments)->filter(fn (array $row): bool => mb_strtolower($row['name']) === 'efectivo')->sum('total');
        $cashExpense = collect($expenses)->filter(fn (array $row): bool => mb_strtolower($row['name']) === 'efectivo')->sum('total');

        return array_merge($pos, $space, [
            'opening' => $opening, 'sales_total' => $paymentMethodId ? $posIncome : $pos['sales_total'],
            'sales_count' => $pos['sales_count'], 'pos_lodging_total' => $pos['lodging_total'],
            'lodging_total' => $space['lodging_total'] + ($paymentMethodId ? 0 : $pos['lodging_total']),
            'income_total' => $space['income_total'] + $posIncome,
            'expenses' => $space['expenses'] + $posExpense,
            'available' => $opening + $cashIncome - $cashExpense,
            'payments' => $payments, 'expense_payments' => $expenses, 'method_balances' => $balances,
            'sales' => $pos['sales'], 'pos_expense_details' => $pos['expense_details'],
        ]);
    }
}
