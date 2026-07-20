<?php

namespace App\Http\Requests\Matchday;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReorderMatchdayDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('matchdays.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'date_id' => ['required', 'integer', 'exists:matchday_dates,id'],
            'direction' => ['required', Rule::in(['up', 'down'])],
        ];
    }
}
