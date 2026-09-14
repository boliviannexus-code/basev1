<?php

namespace App\Http\Requests\TournamentModification;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class SubstituteTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('tournament-modifications.create') ?? false)
            && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'tournament_registration_id' => ['required', 'integer', 'exists:tournament_registrations,id'],
            'incoming_team_id' => ['required', 'integer', 'exists:teams,id'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'confirm_substitution' => ['accepted'],
        ];
    }
}
