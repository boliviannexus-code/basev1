<?php

namespace App\Http\Requests\Spaces\Shared;

use App\Models\Space;
use App\Models\SpaceRoom;
use Illuminate\Foundation\Http\FormRequest;

class StoreSharedRoomPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $space = $this->route('space');
        $room = $this->route('room');

        return $this->user()?->can('spaces.create') === true
            && $space instanceof Space
            && $room instanceof SpaceRoom
            && (int) $space->company_id === (int) $this->user()->company_id
            && (int) $room->space_id === (int) $space->id
            && $space->spaceMode?->slug === 'compartido';
    }

    public function rules(): array
    {
        return [
            'main_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'gallery_photos' => ['nullable', 'array', 'max:3'],
            'gallery_photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}
