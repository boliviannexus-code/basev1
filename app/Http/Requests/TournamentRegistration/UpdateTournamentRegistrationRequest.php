<?php

namespace App\Http\Requests\TournamentRegistration;

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
            'status' => ['required', Rule::in(['registered', 'withdrawn'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
