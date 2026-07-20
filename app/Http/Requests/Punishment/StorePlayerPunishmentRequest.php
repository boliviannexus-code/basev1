<?php

namespace App\Http\Requests\Punishment;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerPunishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('punishments.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'red_card_article_id' => ['required', 'integer', 'exists:red_card_articles,id'],
            'duration_type' => ['required', Rule::in(['months', 'years', 'indefinite'])],
            'duration_value' => ['nullable', 'integer', 'min:1', 'max:100'],
            'starts_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
