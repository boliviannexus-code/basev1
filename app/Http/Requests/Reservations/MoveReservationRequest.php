<?php

namespace App\Http\Requests\Reservations;

use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('reservations.manage') === true
            || $this->user()?->can('occupancy.manage') === true)
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'resource_type' => ['required', Rule::in(['private_space', 'shared_room', 'shared_bed_unit'])],
            'space_id' => [
                'required',
                'integer',
                Rule::exists((new Space)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'space_room_id' => [
                'nullable',
                'integer',
                Rule::exists((new SpaceRoom)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'room_bed_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new RoomBedUnit)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query
                    ->where('company_id', $companyId)
                    ->where('status', 'active')),
            ],
            'check_in' => ['required', 'date', 'after_or_equal:today'],
            'check_out' => ['required', 'date', 'after:check_in'],
            'price_per_night' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_id' => $this->filled('space_id') ? (int) $this->input('space_id') : null,
            'space_room_id' => $this->filled('space_room_id') ? (int) $this->input('space_room_id') : null,
            'room_bed_unit_id' => $this->filled('room_bed_unit_id') ? (int) $this->input('room_bed_unit_id') : null,
            'price_per_night' => $this->filled('price_per_night') ? round((float) $this->input('price_per_night'), 2) : null,
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
