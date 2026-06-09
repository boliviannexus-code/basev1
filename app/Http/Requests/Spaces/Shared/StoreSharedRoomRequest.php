<?php

namespace App\Http\Requests\Spaces\Shared;

use App\Models\BathroomType;
use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSharedRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');

        return $this->user()?->can('spaces.create') === true
            && $space instanceof Space
            && (int) $space->company_id === (int) $this->user()->company_id
            && $space->spaceMode?->slug === 'compartido';
    }

    public function rules(): array
    {
        $space = $this->route('space');
        $room = $this->route('room');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique((new SpaceRoom)->getTable(), 'name')
                    ->where('space_id', $space?->id)
                    ->where('company_id', $space?->company_id)
                    ->whereNull('deleted_at')
                    ->ignore($room?->id),
            ],
            'bathroom_type_id' => [
                'required',
                Rule::exists((new BathroomType)->getTable(), 'id')->where('is_active', true),
            ],
            'status' => ['required', Rule::in(['draft', 'active', 'inactive'])],
            'sale_mode' => ['required', Rule::in(SpaceRoom::SALE_MODES)],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'Ya existe una habitacion con ese nombre en este alojamiento.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sale_mode' => $this->input('sale_mode') ?: 'full_room',
        ]);
    }

    public function room(): ?SpaceRoom
    {
        return $this->route('room');
    }
}
