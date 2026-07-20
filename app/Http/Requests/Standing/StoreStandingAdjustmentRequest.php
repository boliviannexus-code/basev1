<?php

namespace App\Http\Requests\Standing;

use Illuminate\Foundation\Http\FormRequest;

class StoreStandingAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('standings.adjust') ?? false;
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'category_id' => ['required', 'integer', 'exists:division_categories,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'series' => ['required', 'string', 'max:30'],
            'points_adjustment' => ['required', 'integer', 'min:-99', 'max:99', 'not_in:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }
}
