<?php

namespace App\Http\Requests\Availability;

use App\Models\AvailabilityDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BulkUpdateAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('availability.manage') === true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', Rule::in(['all', 'private', 'shared'])],
            'space_id' => ['nullable', 'integer'],
            'space_room_id' => ['nullable', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'apply_price' => ['nullable', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'apply_status' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(AvailabilityDay::STATUSES)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->boolean('apply_price') && ! $this->boolean('apply_status')) {
                    $validator->errors()->add('apply_price', 'Selecciona precio, estado o ambos para aplicar en bloque.');
                }

                if ($this->boolean('apply_status') && ! filled($this->input('status'))) {
                    $validator->errors()->add('status', 'Selecciona el estado que se aplicara en bloque.');
                }

                if (filled($this->input('space_room_id')) && ! filled($this->input('space_id'))) {
                    $validator->errors()->add('space_id', 'Selecciona el alojamiento para aplicar a una habitacion especifica.');
                }
            },
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'type' => $this->input('type') ?: 'all',
            'space_id' => filled($this->input('space_id')) ? $this->input('space_id') : null,
            'space_room_id' => filled($this->input('space_room_id')) ? $this->input('space_room_id') : null,
            'price' => filled($this->input('price')) ? $this->input('price') : null,
        ]);
    }
}
