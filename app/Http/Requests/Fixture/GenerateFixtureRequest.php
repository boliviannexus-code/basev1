<?php

namespace App\Http\Requests\Fixture;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateFixtureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('fixtures.generate') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'first_phase_rounds' => ['required', 'integer', Rule::in([1, 2])],
            'qualifiers_per_series' => ['required', 'integer', 'min:0'],
            'second_phase_mode' => ['required', Rule::in(['knockout', 'league', 'accumulative'])],
            'fill_rule' => ['nullable', Rule::in(['best_thirds'])],
            'third_place' => ['nullable', 'boolean'],
            'league_rounds' => ['nullable', 'integer', Rule::in([1, 2])],
            'league_champion_rule' => ['nullable', Rule::in(['table', 'top_two_final', 'semifinal_final'])],
            'stage_leg_modes' => ['nullable', 'array'],
            'stage_leg_modes.*' => [Rule::in(['single', 'double'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fill_rule' => $this->input('fill_rule') ?: 'best_thirds',
            'third_place' => $this->boolean('third_place'),
            'league_rounds' => (int) ($this->input('league_rounds') ?: 1),
            'league_champion_rule' => $this->input('league_champion_rule') ?: 'table',
        ]);
    }
}
