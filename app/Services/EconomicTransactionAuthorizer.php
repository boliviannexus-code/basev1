<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class EconomicTransactionAuthorizer
{
    public function userForPin(User $sessionUser, string $pin, string $errorBag): User
    {
        $pin = trim($pin);

        $matches = User::query()
            ->where('company_id', $sessionUser->company_id)
            ->where('is_active', true)
            ->whereNotNull('transaction_pin')
            ->get()
            ->filter(fn (User $user): bool => Hash::check($pin, (string) $user->transaction_pin))
            ->values();

        if ($matches->count() !== 1) {
            throw ValidationException::withMessages([
                'transaction_pin' => $matches->isEmpty()
                    ? 'El codigo de caja no es valido.'
                    : 'El codigo de caja esta duplicado. Cambia el codigo de uno de los usuarios.',
            ])->errorBag($errorBag);
        }

        return $matches->first();
    }
}
