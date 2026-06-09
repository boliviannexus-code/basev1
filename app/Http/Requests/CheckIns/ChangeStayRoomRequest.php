<?php

namespace App\Http\Requests\CheckIns;

use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangeStayRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true
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
            'price_per_night_bob' => ['required', 'numeric', 'min:0'],
            'night_prices' => ['nullable', 'array'],
            'night_prices.*' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'space_id' => $this->filled('space_id') ? (int) $this->input('space_id') : null,
            'space_room_id' => $this->filled('space_room_id') ? (int) $this->input('space_room_id') : null,
            'room_bed_unit_id' => $this->filled('room_bed_unit_id') ? (int) $this->input('room_bed_unit_id') : null,
            'night_prices' => collect($this->input('night_prices', []))
                ->filter(fn ($value, $date): bool => filled($date) && filled($value))
                ->mapWithKeys(fn ($value, $date): array => [(string) $date => round((float) $value, 2)])
                ->all(),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
        ]);
    }
}
