<?php

namespace App\Http\Requests\RedCard;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRedCardSanctionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('red-cards.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'team_side' => ['required', Rule::in(['home', 'away'])],
            'tournament_team_player_id' => ['required', 'integer', 'exists:tournament_team_players,id'],
            'jersey_number' => ['required', 'integer', 'min:0', 'max:999'],
            'red_card_article_id' => ['required', 'integer', 'exists:red_card_articles,id'],
            'action_detail' => ['required', 'string', 'min:3', 'max:2000'],
            'suspended_matches' => ['required', 'integer', 'min:0', 'max:999'],
        ];
    }
}
