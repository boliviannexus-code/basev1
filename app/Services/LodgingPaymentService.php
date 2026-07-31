<?php

namespace App\Services;

use App\Models\AccountStatement;
use App\Models\ExchangeRate;
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
        $remainingBob = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($selectedStay, $user, $data, $cashRegister, $paymentMethod, &$remainingBob): array {
            $payments = [];
            $stays = $selectedStay->checkInGroup
                ->stays()
                ->with('accountStatement')
                ->orderByRaw('id = ? desc', [$selectedStay->id])
                ->orderBy('id')
                ->get();

            foreach ($stays as $stay) {
                if ($remainingBob <= 0) {
                    break;
                }

                $statement = $stay->accountStatement ?: $this->accountStatements->createForStay($stay);
                $statement = $this->accountStatements->recalculate($statement);
                $statementCurrency = $statement->currency ?: $stay->currency;
                $exchangeRate = $this->exchangeRateForStay($stay, $statementCurrency === 'USD');
                $statementBalanceBob = $statementCurrency === 'USD'
                    ? round((float) $statement->balance * $exchangeRate, 2)
                    : (float) $statement->balance;
                $portionBob = min($remainingBob, $statementBalanceBob);

                if ($portionBob <= 0) {
                    continue;
                }

                $payments[] = $this->recordOne($stay, $statement, $user, $cashRegister, $paymentMethod, $portionBob, $data['reference'] ?? null);
                $remainingBob = round($remainingBob - $portionBob, 2);
            }

            if ($remainingBob > 0) {
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

    private function recordOne(Stay $stay, AccountStatement $statement, User $user, SpaceCashRegister $cashRegister, PaymentMethod $paymentMethod, float $amountBob, ?string $reference): SpaceCashLodgingPayment
    {
        $statement = $this->accountStatements->recalculate($statement);
        $currency = $statement->currency ?: $stay->currency;
        $exchangeRate = $this->exchangeRateForStay($stay, $currency === 'USD');
        $amount = $currency === 'USD'
            ? round($amountBob / $exchangeRate, 2)
            : $amountBob;

        if ($amount <= 0 || $amount > (float) $statement->balance) {
            throw ValidationException::withMessages([
                'amount' => 'El monto debe ser mayor a cero y no puede superar el saldo.',
            ])->errorBag('stayPayment');
        }

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
            'amount_original' => $amountBob,
            'currency_original' => 'BOB',
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

    private function exchangeRateForStay(Stay $stay, bool $required): float
    {
        $exchangeRate = (float) ($stay->exchange_rate ?: ExchangeRate::currentRateForCompany((int) $stay->company_id));

        if ($exchangeRate <= 0) {
            if (! $required) {
                return 1;
            }

            throw ValidationException::withMessages([
                'amount' => 'Configura un tipo de cambio vigente para mostrar referencia en dolares.',
            ])->errorBag('stayPayment');
        }

        return $exchangeRate;
    }

    private function openRegisterOrFail(User $user): SpaceCashRegister
    {
        $cashRegister = $this->spaceCashRegisters->currentForUser($user);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'transaction_pin' => 'El usuario dueño del codigo debe tener una caja abierta.',
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
