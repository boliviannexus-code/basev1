<?php

namespace App\Http\Requests\Availability;

use App\Models\AvailabilityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAvailabilityStatusRequest extends FormRequest
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
            'date' => ['required', 'date', 'after_or_equal:today'],
            'status' => ['required', Rule::in(AvailabilityStatus::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_room_id' => filled($this->input('space_room_id')) ? $this->input('space_room_id') : null,
            'room_bed_unit_id' => filled($this->input('room_bed_unit_id')) ? $this->input('room_bed_unit_id') : null,
            'notes' => filled($this->input('notes')) ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
