<?php

namespace App\Http\Requests\TournamentRegistration;

use App\Models\TournamentRegistration;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTournamentRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $registration = $this->route('tournamentRegistration');

        return ($this->user()?->can('tournament-registrations.update') ?? false)
            && $registration !== null
            && CompanyContext::belongsToUser($registration->company_id, $this->user());
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:division_categories,id'],
            'series' => ['required', Rule::in(array_keys(TournamentRegistration::SERIES))],
            'status' => ['required', Rule::in(['registered', 'withdrawn'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $registration = $this->route('tournamentRegistration');

        $this->merge([
            'series' => $this->input('series') ?: ($registration?->series ?? 'unica'),
        ]);
    }
}
