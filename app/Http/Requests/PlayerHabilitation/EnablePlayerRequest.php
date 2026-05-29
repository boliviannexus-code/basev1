<?php

namespace App\Http\Requests\PlayerHabilitation;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class EnablePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-habilitations.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'tournament_id' => ['required', 'integer'],
            'team_player_id' => ['required', 'integer'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
