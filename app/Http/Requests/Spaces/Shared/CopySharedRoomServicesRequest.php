<?php

namespace App\Http\Requests\Spaces\Shared;

use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CopySharedRoomServicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $room = $this->route('room');

        return $this->user()?->can('spaces.create') === true
            && $space instanceof Space
            && $room instanceof SpaceRoom
            && (int) $space->company_id === (int) $this->user()->company_id
            && (int) $room->company_id === (int) $space->company_id
            && (int) $room->space_id === (int) $space->id
            && $space->spaceMode?->slug === 'compartido';
    }

    public function rules(): array
    {
        return [
            'target_room_ids' => ['required', 'array', 'min:1'],
            'target_room_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $space = $this->route('space');
                $room = $this->route('room');
                $targetRoomIds = collect($this->input('target_room_ids', []))
                    ->map(fn ($id): int => (int) $id)
                    ->values();

                if ($targetRoomIds->contains((int) $room->id)) {
                    $validator->errors()->add('target_room_ids', 'No puedes copiar servicios hacia la misma habitacion origen.');

                    return;
                }

                $validTargetCount = $space->rooms()
                    ->whereIn('id', $targetRoomIds)
                    ->count();

                if ($validTargetCount !== $targetRoomIds->count()) {
                    $validator->errors()->add('target_room_ids', 'Todas las habitaciones destino deben pertenecer al mismo espacio.');
                }
            },
        ];
    }
}
