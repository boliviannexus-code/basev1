<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTeamUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('teams.approve-updates') ?? false;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
