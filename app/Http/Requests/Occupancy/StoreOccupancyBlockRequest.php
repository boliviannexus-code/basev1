<?php

namespace App\Http\Requests\Occupancy;

use App\Models\OccupancyBlock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOccupancyBlockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true;
    }

    public function rules(): array
    {
        return [
            'space_id' => ['required', 'integer'],
            'space_room_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(OccupancyBlock::TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
