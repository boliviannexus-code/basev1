<?php

namespace App\Http\Requests\MatchReport;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMatchReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('match-reports.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'referee_1' => ['nullable', 'string', 'max:255'],
            'referee_2' => ['nullable', 'string', 'max:255'],
            'referee_3' => ['nullable', 'string', 'max:255'],
            'home_brought_ball' => ['sometimes', 'boolean'],
            'away_brought_ball' => ['sometimes', 'boolean'],
            'home_present' => ['sometimes', 'boolean'],
            'away_present' => ['sometimes', 'boolean'],
            'home_paid_court_fee' => ['sometimes', 'boolean'],
            'away_paid_court_fee' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
