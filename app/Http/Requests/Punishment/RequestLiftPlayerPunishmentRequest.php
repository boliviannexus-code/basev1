<?php

namespace App\Http\Requests\Punishment;

use Illuminate\Foundation\Http\FormRequest;

class RequestLiftPlayerPunishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('punishments.request-lift') ?? false;
    }

    public function rules(): array
    {
        return [
            'lift_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
