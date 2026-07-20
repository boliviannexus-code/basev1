<?php

namespace App\Http\Requests\TournamentRegistration;

use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('tournament-registrations.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer', 'exists:tournaments,id'],
            'category_id' => ['required', 'integer', 'exists:division_categories,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'series' => ['required', Rule::in(array_keys(TournamentRegistration::SERIES))],
            'status' => ['required', Rule::in(['registered', 'withdrawn'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'series' => $this->input('series') ?: 'unica',
        ]);
    }
}
