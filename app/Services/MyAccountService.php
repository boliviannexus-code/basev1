<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class MyAccountService
{
    public function updatePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => Hash::make($password),
            'remember_token' => Str::random(60),
        ])->save();
    }

    public function updateTransactionPin(User $user, string $transactionPin): void
    {
        $user->forceFill([
            'transaction_pin' => Hash::make($transactionPin),
        ])->save();
    }
}
