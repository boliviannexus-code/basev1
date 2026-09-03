<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

final class UpdateOwnTransactionPinRequest extends FormRequest
{
    protected $errorBag = 'transactionPinUpdate';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'transaction_pin' => ['required', 'digits:4', 'confirmed'],
            'transaction_pin_confirmation' => ['required', 'digits:4'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.current_password' => 'La contraseña actual no es correcta.',
            'transaction_pin.digits' => 'El código de caja debe tener exactamente 4 dígitos.',
            'transaction_pin.confirmed' => 'La confirmación del código de caja no coincide.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('transaction_pin') || ! $this->user()?->company_id) {
                    return;
                }

                $pin = (string) $this->input('transaction_pin');
                $duplicateExists = User::query()
                    ->where('company_id', $this->user()->company_id)
                    ->whereKeyNot($this->user()->getKey())
                    ->where('is_active', true)
                    ->whereNotNull('transaction_pin')
                    ->get(['id', 'transaction_pin'])
                    ->contains(fn (User $user): bool => Hash::check($pin, (string) $user->transaction_pin));

                if ($duplicateExists) {
                    $validator->errors()->add('transaction_pin', 'Este código de caja ya está asignado a otro usuario de la empresa.');
                }
            },
        ];
    }
}
