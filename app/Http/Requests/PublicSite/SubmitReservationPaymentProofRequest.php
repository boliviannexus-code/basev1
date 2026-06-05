<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class SubmitReservationPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:5120'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payment_reference' => 'referencia de pago',
            'payment_proof' => 'comprobante de pago',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_reference' => $this->filled('payment_reference') ? trim((string) $this->input('payment_reference')) : null,
        ]);
    }
}
