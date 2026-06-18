<?php

namespace App\Http\Requests\Reservations;

use App\Http\Requests\CheckIns\StoreCheckInRequest;

class StoreInternalReservationRequest extends StoreCheckInRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['check_in_date'] = ['required', 'date', 'after_or_equal:today'];
        $rules['main_guest.phone'] = ['nullable', 'string', 'max:40'];
        $rules['phone'] = ['nullable', 'string', 'max:40'];

        return $rules;
    }

    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'check_in_date' => 'fecha de ingreso de la reserva',
            'check_out_date' => 'fecha de salida de la reserva',
            'main_guest.phone' => 'telefono',
            'phone' => 'telefono',
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'main_guest' => [
                ...$this->input('main_guest', []),
                'phone' => $this->filled('main_guest.phone') || $this->filled('phone')
                    ? trim((string) $this->input('main_guest.phone', $this->input('phone')))
                    : null,
            ],
        ]);
    }
}
