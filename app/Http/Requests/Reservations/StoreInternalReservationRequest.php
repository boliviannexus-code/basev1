<?php

namespace App\Http\Requests\Reservations;

use App\Http\Requests\CheckIns\StoreCheckInRequest;

class StoreInternalReservationRequest extends StoreCheckInRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['check_in_date'] = ['required', 'date', 'after_or_equal:today'];
        $rules['main_guest.document_number'] = ['nullable', 'string', 'max:80'];
        $rules['main_guest.birth_country_id'][0] = 'nullable';
        $rules['stays.*.guests.*.document_number'] = ['nullable', 'string', 'max:80'];
        $rules['stays.*.guests.*.birth_country_id'][0] = 'nullable';
        $rules['main_guest.phone'] = ['nullable', 'string', 'max:40'];
        $rules['phone'] = ['nullable', 'string', 'max:40'];

        return $rules;
    }

    protected function requiredAdditionalGuestFields(): array
    {
        return ['document_type', 'first_name', 'last_name', 'birth_date'];
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
