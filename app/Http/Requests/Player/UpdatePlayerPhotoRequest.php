<?php

namespace App\Http\Requests\Player;

use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $player = $this->route('player');
        $user = $this->user();

        if (! $player instanceof Player || ! $user?->can('players.update') || ! CompanyContext::canOperate($user)) {
            return false;
        }

        if (CompanyContext::isGlobalAdmin($user)) {
            return true;
        }

        return (int) $player->company_id === (int) CompanyContext::id($user);
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.image' => 'El archivo debe ser una imagen valida. Verifica que no este corrupta.',
            'photo.mimes' => 'La foto debe estar en formato JPG, JPEG, PNG o WebP.',
            'photo.max' => 'La foto no debe superar 10 MB.',
        ];
    }
}
