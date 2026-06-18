<?php

namespace App\Services;

use App\Models\PaymentMethod;
use App\Models\ReservationGroup;
use App\Models\SpaceCashRegister;
use App\Models\SpaceCashReservationPayment;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationPaymentService
{
    public function __construct(
        private readonly AccountStatementService $accountStatements,
        private readonly SpaceCashRegisterService $spaceCashRegisters,
    ) {}

    public function recordForGroup(ReservationGroup $group, User $user, array $data): string
    {
        $this->ensureGroupOwnership($group, $user);
        $paymentMethod = $this->paymentMethodOrFail((int) $data['payment_method_id'], $user);
        $amount = round((float) $data['amount'], 2);

        return DB::transaction(function () use ($group, $user, $data, $paymentMethod, $amount): string {
            $cashRegister = $this->openRegisterOrFail($user, true);
            $statement = $this->accountStatements->recalculate($group->accountStatement ?: $this->accountStatements->createForReservationGroup($group));

            if ($amount <= 0 || $amount > (float) $statement->balance) {
                throw ValidationException::withMessages([
                    'amount' => 'El monto debe ser mayor a cero y no puede superar el saldo.',
                ])->errorBag('reservationPayment');
            }

            $receiptNumber = $this->spaceCashRegisters->nextReceiptNumber($user);
            $description = 'Adelanto reserva '.$receiptNumber.' - '.$paymentMethod->name;
            if (filled($data['reference'] ?? null)) {
                $description .= ' - '.$data['reference'];
            }

            $item = $this->accountStatements->recordReservationPayment($group, $amount, $description, $statement->currency);
            $statement = $this->accountStatements->recalculate($statement->refresh());
            $currency = $statement->currency ?: $group->currency;
            $exchangeRate = $currency === 'USD' ? 1 : 1;

            SpaceCashReservationPayment::query()->create([
                'company_id' => $group->company_id,
                'space_cash_register_id' => $cashRegister->id,
                'user_id' => $user->id,
                'account_statement_id' => $statement->id,
                'account_statement_item_id' => $item->id,
                'reservation_group_id' => $group->id,
                'payment_method_id' => $paymentMethod->id,
                'receipt_number' => $receiptNumber,
                'reference' => $data['reference'] ?? null,
                'amount_original' => $amount,
                'currency_original' => $currency,
                'exchange_rate' => $exchangeRate,
                'amount_bob' => $amount,
                'status' => 'active',
            ]);

            $group->update([
                'advance_amount' => $statement->payments_total,
                'balance_amount' => $statement->balance,
                'payment_status' => (float) $statement->balance <= 0 ? 'validated' : 'partial',
                'payment_method' => $paymentMethod->name,
                'payment_reference' => $data['reference'] ?? $receiptNumber,
            ]);

            $group->reservations()->update([
                'payment_status' => (float) $statement->balance <= 0 ? 'validated' : 'submitted',
                'payment_method' => $paymentMethod->name,
                'payment_reference' => $data['reference'] ?? $receiptNumber,
            ]);

            return $receiptNumber;
        });
    }

    public function availableBalance(ReservationGroup $group): float
    {
        $statement = $group->accountStatement ?: $this->accountStatements->createForReservationGroup($group);

        return (float) $this->accountStatements->recalculate($statement)->balance;
    }

    private function ensureGroupOwnership(ReservationGroup $group, User $user): void
    {
        abort_unless((int) $group->company_id === (int) $user->company_id, 404);
    }

    private function openRegisterOrFail(User $user, bool $lock = false): SpaceCashRegister
    {
        $cashRegister = $lock
            ? SpaceCashRegister::query()
                ->where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->where('status', 'open')
                ->lockForUpdate()
                ->first()
            : $this->spaceCashRegisters->currentForUser($user);

        if (! $cashRegister) {
            throw ValidationException::withMessages([
                'amount' => 'Debes iniciar caja antes de registrar un cobro.',
            ])->errorBag('reservationPayment');
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
            ])->errorBag('reservationPayment');
        }

        return $method;
    }
}
