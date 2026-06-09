<?php

namespace App\Services;

use App\Models\AccountStatement;
use App\Models\PaymentMethod;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashRegister;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LodgingPaymentService
{
    public function __construct(
        private readonly AccountStatementService $accountStatements,
        private readonly SpaceCashRegisterService $spaceCashRegisters,
    ) {}

    public function recordForStay(Stay $stay, User $user, array $data): array
    {
        $this->ensureStayOwnership($stay, $user);
        $cashRegister = $this->openRegisterOrFail($user);
        $paymentMethod = $this->paymentMethodOrFail((int) $data['payment_method_id'], $user);
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($stay, $user, $data, $cashRegister, $paymentMethod, $amount): array {
            $statement = $stay->accountStatement ?: $this->accountStatements->createForStay($stay);

            return [$this->recordOne($stay, $statement, $user, $cashRegister, $paymentMethod, $amount, $data['reference'] ?? null)];
        });
    }

    public function recordForGroup(Stay $selectedStay, User $user, array $data): array
    {
        $this->ensureStayOwnership($selectedStay, $user);
        $cashRegister = $this->openRegisterOrFail($user);
        $paymentMethod = $this->paymentMethodOrFail((int) $data['payment_method_id'], $user);
        $remaining = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($selectedStay, $user, $data, $cashRegister, $paymentMethod, &$remaining): array {
            $payments = [];
            $stays = $selectedStay->checkInGroup
                ->stays()
                ->with('accountStatement')
                ->orderByRaw('id = ? desc', [$selectedStay->id])
                ->orderBy('id')
                ->get();

            foreach ($stays as $stay) {
                if ($remaining <= 0) {
                    break;
                }

                $statement = $stay->accountStatement ?: $this->accountStatements->createForStay($stay);
                $statement = $this->accountStatements->recalculate($statement);
                $portion = min($remaining, (float) $statement->balance);

                if ($portion <= 0) {
                    continue;
                }

                $payments[] = $this->recordOne($stay, $statement, $user, $cashRegister, $paymentMethod, $portion, $data['reference'] ?? null);
                $remaining = round($remaining - $portion, 2);
            }

            if ($remaining > 0) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto no puede superar el saldo del check-in.',
                ])->errorBag('stayPayment');
            }

            return $payments;
        });
    }

    public function availableScopeBalance(Stay $stay, string $scope): float
    {
        if ($scope === 'group') {
            return (float) $stay->checkInGroup
                ->stays()
                ->with('accountStatement')
                ->get()
                ->sum(fn (Stay $item): float => (float) $this->accountStatements->recalculate($item->accountStatement ?: $this->accountStatements->createForStay($item))->balance);
        }

        return (float) $this->accountStatements->recalculate($stay->accountStatement ?: $this->accountStatements->createForStay($stay))->balance;
    }

    private function recordOne(Stay $stay, AccountStatement $statement, User $user, SpaceCashRegister $cashRegister, PaymentMethod $paymentMethod, float $amount, ?string $reference): SpaceCashLodgingPayment
    {
        $statement = $this->accountStatements->recalculate($statement);

        if ($amount <= 0 || $amount > (float) $statement->balance) {
            throw ValidationException::withMessages([
                'amount' => 'El monto debe ser mayor a cero y no puede superar el saldo.',
            ])->errorBag('stayPayment');
        }

        $currency = $statement->currency ?: $stay->currency;
        $exchangeRate = $currency === 'USD' ? (float) ($stay->exchange_rate ?: 1) : 1;
        $amountBob = round($currency === 'USD' ? $amount * $exchangeRate : $amount, 2);
        $receiptNumber = $this->nextReceiptNumber($cashRegister);

        $item = $this->accountStatements->recordPayment($stay, $amount, 'Pago hospedaje '.$receiptNumber.' - '.$paymentMethod->name, $currency);

        $payment = SpaceCashLodgingPayment::query()->create([
            'company_id' => $stay->company_id,
            'space_cash_register_id' => $cashRegister->id,
            'user_id' => $user->id,
            'account_statement_id' => $statement->id,
            'account_statement_item_id' => $item->id,
            'stay_id' => $stay->id,
            'check_in_group_id' => $stay->check_in_group_id,
            'payment_method_id' => $paymentMethod->id,
            'receipt_number' => $receiptNumber,
            'reference' => $reference,
            'amount_original' => $amount,
            'currency_original' => $currency,
            'exchange_rate' => $exchangeRate,
            'amount_bob' => $amountBob,
            'status' => 'active',
        ]);

        return $payment;
    }

    private function nextReceiptNumber(SpaceCashRegister $cashRegister): string
    {
        return $this->spaceCashRegisters->nextReceiptNumber($cashRegister->user);
    }

    private function openRegisterOrFail(User $user): SpaceCashRegister
    {
        $cashRegister = $this->spaceCashRegisters->currentForUser($user);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja antes de registrar un cobro.',
            ])->errorBag('stayPayment');
        }

        return $cashRegister;
    }

    private function paymentMethodOrFail(int $id, User $user): PaymentMethod
    {
        $method = PaymentMethod::query()
            ->whereKey($id)
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->first();

        if (! $method) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Selecciona un metodo de pago activo.',
            ])->errorBag('stayPayment');
        }

        return $method;
    }

    private function ensureStayOwnership(Stay $stay, User $user): void
    {
        abort_unless((int) $stay->company_id === (int) $user->company_id, 404);
    }
}
