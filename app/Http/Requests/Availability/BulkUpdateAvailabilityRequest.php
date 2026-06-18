<?php

namespace App\Http\Requests\Availability;

use App\Models\AvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('availability.manage') === true;
    }

    public function rules(): array
    {
        return [
            'space_id' => ['required', 'integer'],
            'space_room_id' => ['nullable', 'integer'],
            'room_bed_unit_id' => ['nullable', 'integer'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::in(AvailabilityStatus::STATUSES)],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'is_public_online' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_room_id' => filled($this->input('space_room_id')) ? $this->input('space_room_id') : null,
            'room_bed_unit_id' => filled($this->input('room_bed_unit_id')) ? $this->input('room_bed_unit_id') : null,
            'status' => filled($this->input('status')) ? $this->input('status') : null,
            'price' => filled($this->input('price')) ? $this->input('price') : null,
            'is_public_online' => $this->has('is_public_online') && filled($this->input('is_public_online')) ? $this->boolean('is_public_online') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('status') === null && $this->input('price') === null && $this->input('is_public_online') === null) {
                $validator->errors()->add('status', 'Selecciona un estado, ingresa un precio o cambia la reserva publica.');
            }
        });
    }
}
