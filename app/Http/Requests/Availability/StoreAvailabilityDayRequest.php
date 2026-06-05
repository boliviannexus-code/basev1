<?php

namespace App\Http\Requests\Availability;

use App\Models\AvailabilityDay;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilityDayRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(AvailabilityDay::STATUSES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_room_id' => filled($this->input('space_room_id')) ? $this->input('space_room_id') : null,
            'price' => filled($this->input('price')) ? $this->input('price') : null,
        ]);
    }
}
