<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UnifiedCashSessionService
{
    public function open(User $user, float $amount): CashRegister
    {
        if (! Schema::hasColumn('space_cash_registers', 'cash_register_id')) {
            throw ValidationException::withMessages(['opening_amount' => 'Cierra las cajas existentes y ejecuta la migracion antes de abrir una nueva caja.']);
        }
        if (! $user->company_id) {
            throw ValidationException::withMessages(['opening_amount' => 'Tu usuario no esta vinculado a una empresa.']);
        }

        return DB::transaction(function () use ($user, $amount): CashRegister {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            if (CashRegister::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->where('status', 'open')->exists()
                || SpaceCashRegister::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->where('status', 'open')->exists()) {
                throw ValidationException::withMessages(['opening_amount' => 'Ya tienes una caja abierta.']);
            }
            $register = CashRegister::query()->create([
                'company_id' => $user->company_id, 'user_id' => $user->id,
                'opening_amount' => $amount, 'opened_at' => now(), 'status' => 'open',
            ]);
            SpaceCashRegister::query()->create([
                'company_id' => $user->company_id, 'user_id' => $user->id,
                'cash_register_id' => $register->id, 'opening_amount' => 0,
                'opened_at' => $register->opened_at, 'status' => 'open',
            ]);

            return $register;
        });
    }

    public function close(User $user, float $amount, string $bag): CashRegister
    {
        return DB::transaction(function () use ($user, $amount, $bag): CashRegister {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $register = CashRegister::query()->where('company_id', $user->company_id)->where('user_id', $user->id)->where('status', 'open')->lockForUpdate()->first();
            $space = $register ? SpaceCashRegister::query()->where('cash_register_id', $register->id)->where('status', 'open')->lockForUpdate()->first() : null;
            if (! $register || ! $space) {
                throw ValidationException::withMessages(['closing_amount' => 'No tienes una caja unificada abierta para cerrar.'])->errorBag($bag);
            }
            $closedAt = now();
            $register->update(['closing_amount' => $amount, 'closed_at' => $closedAt, 'status' => 'closed']);
            $space->update(['closing_amount' => $amount, 'closed_at' => $closedAt, 'status' => 'closed']);

            return $register->refresh();
        });
    }
}
