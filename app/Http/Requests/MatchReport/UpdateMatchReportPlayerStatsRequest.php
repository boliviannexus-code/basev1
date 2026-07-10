<?php

namespace App\Http\Requests\MatchReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatchReportPlayerStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('match-reports.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'field' => ['required', Rule::in(['goals', 'yellow_cards', 'red_cards'])],
            'delta' => ['required', 'integer', Rule::in([-1, 1])],
        ];
    }
}
