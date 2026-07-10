<?php

namespace App\Http\Requests\TournamentRegistration;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTournamentRegistrationTeamNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('tournament-registrations.update') ?? false)
            && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'team_number' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
