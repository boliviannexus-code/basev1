<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_document' => ['nullable', 'string', 'max:60'],
            'guest_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'guest_name' => 'nombre',
            'guest_email' => 'correo',
            'guest_phone' => 'telefono',
            'guest_country' => 'pais',
            'guest_document' => 'documento',
            'guest_notes' => 'notas',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'guest_name' => $this->filled('guest_name') ? trim((string) $this->input('guest_name')) : null,
            'guest_email' => $this->filled('guest_email') ? mb_strtolower(trim((string) $this->input('guest_email'))) : null,
            'guest_phone' => $this->filled('guest_phone') ? trim((string) $this->input('guest_phone')) : null,
            'guest_country' => $this->filled('guest_country') ? trim((string) $this->input('guest_country')) : null,
            'guest_document' => $this->filled('guest_document') ? trim((string) $this->input('guest_document')) : null,
        ]);
    }
}
