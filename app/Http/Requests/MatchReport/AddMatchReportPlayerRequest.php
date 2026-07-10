<?php

namespace App\Http\Requests\MatchReport;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddMatchReportPlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('match-reports.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'team_side' => ['required', Rule::in(['home', 'away'])],
            'tournament_team_player_id' => ['required', 'integer', 'exists:tournament_team_players,id'],
            'jersey_number' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
