<?php

namespace App\Http\Requests\Punishment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewLiftPlayerPunishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('punishments.approve-lift') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'lift_review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
