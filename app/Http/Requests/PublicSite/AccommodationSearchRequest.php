<?php

namespace App\Http\Requests\PublicSite;

use Illuminate\Foundation\Http\FormRequest;

class AccommodationSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['nullable', 'string', 'max:120'],
            'destination_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'destination_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'destination_city' => ['nullable', 'string', 'max:120'],
            'destination_state' => ['nullable', 'string', 'max:120'],
            'destination_country' => ['nullable', 'string', 'max:120'],
            'check_in' => ['nullable', 'date', 'required_with:check_out'],
            'check_out' => ['nullable', 'date', 'required_with:check_in', 'after:check_in'],
            'guests' => ['nullable', 'integer', 'min:1', 'max:50'],
            'space_id' => ['nullable', 'integer', 'exists:spaces,id'],
            'space_room_id' => ['nullable', 'integer', 'exists:space_rooms,id'],
            'space_room_ids' => ['nullable', 'array'],
            'space_room_ids.*' => ['integer', 'exists:space_rooms,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'destination' => 'lugar o destino',
            'destination_latitude' => 'latitud del destino',
            'destination_longitude' => 'longitud del destino',
            'destination_city' => 'ciudad del destino',
            'destination_state' => 'departamento del destino',
            'destination_country' => 'pais del destino',
            'check_in' => 'fecha de ingreso',
            'check_out' => 'fecha de salida',
            'guests' => 'personas',
            'space_id' => 'espacio',
            'space_room_id' => 'habitacion',
            'space_room_ids' => 'habitaciones',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'destination' => $this->filled('destination') ? trim((string) $this->input('destination')) : null,
            'destination_city' => $this->filled('destination_city') ? trim((string) $this->input('destination_city')) : null,
            'destination_state' => $this->filled('destination_state') ? trim((string) $this->input('destination_state')) : null,
            'destination_country' => $this->filled('destination_country') ? trim((string) $this->input('destination_country')) : null,
            'guests' => $this->filled('guests') ? (int) $this->input('guests') : 1,
        ]);
    }
}
