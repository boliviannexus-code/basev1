<?php

namespace App\Http\Requests\TournamentRegistration;

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
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'status' => ['required', Rule::in(['registered', 'withdrawn'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
