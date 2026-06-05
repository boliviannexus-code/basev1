<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $guestRules = auth()->check()
            ? ['nullable', Rule::in(['current'])]
            : ['required', Rule::in(['login', 'register'])];

        return [
            'space_id' => ['required', 'integer', 'exists:spaces,id'],
            'space_room_id' => ['nullable', 'integer', 'exists:space_rooms,id'],
            'space_room_ids' => ['nullable', 'array'],
            'space_room_ids.*' => ['integer', 'exists:space_rooms,id'],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'guests' => ['required', 'integer', 'min:1', 'max:50'],
            'guest_name' => ['required', 'string', 'max:120'],
            'guest_email' => ['required', 'email', 'max:160'],
            'guest_phone' => ['nullable', 'string', 'max:40'],
            'guest_country' => ['nullable', 'string', 'max:80'],
            'guest_document' => ['nullable', 'string', 'max:60'],
            'guest_notes' => ['nullable', 'string', 'max:1000'],
            'account_mode' => $guestRules,
            'password' => [
                Rule::requiredIf(fn (): bool => ! auth()->check()),
                'string',
                'min:8',
                'max:120',
            ],
            'password_confirmation' => [
                Rule::requiredIf(fn (): bool => ! auth()->check() && $this->input('account_mode') === 'register'),
                'nullable',
                'same:password',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'space_id' => 'alojamiento',
            'space_room_id' => 'habitacion',
            'space_room_ids' => 'habitaciones',
            'check_in' => 'fecha de ingreso',
            'check_out' => 'fecha de salida',
            'guests' => 'personas',
            'guest_name' => 'nombre',
            'guest_email' => 'correo',
            'guest_phone' => 'telefono',
            'guest_country' => 'pais',
            'guest_document' => 'documento',
            'guest_notes' => 'notas',
            'account_mode' => 'modo de acceso',
            'password' => 'contrasena',
            'password_confirmation' => 'confirmacion de contrasena',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_room_id' => filled($this->input('space_room_id')) ? (int) $this->input('space_room_id') : null,
            'space_room_ids' => collect($this->input('space_room_ids', []))
                ->filter(fn ($id): bool => filled($id))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            'guests' => filled($this->input('guests')) ? (int) $this->input('guests') : 1,
            'guest_name' => $this->filled('guest_name') ? trim((string) $this->input('guest_name')) : null,
            'guest_email' => $this->filled('guest_email') ? mb_strtolower(trim((string) $this->input('guest_email'))) : null,
            'guest_phone' => $this->filled('guest_phone') ? trim((string) $this->input('guest_phone')) : null,
            'guest_country' => $this->filled('guest_country') ? trim((string) $this->input('guest_country')) : null,
            'guest_document' => $this->filled('guest_document') ? trim((string) $this->input('guest_document')) : null,
        ]);
    }
}
