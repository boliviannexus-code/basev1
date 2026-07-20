<?php

namespace App\Http\Requests\LeagueSetting;

use App\Models\LeagueSetting;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeagueSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('league-settings.update') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $rules = [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ];

        foreach (LeagueSetting::COST_FIELDS as $field) {
            $rules[$field] = ['required', 'numeric', 'min:0', 'max:99999999.99'];
        }

        $rules['max_enabled_players_per_team_category'] = ['required', 'integer', 'min:0', 'max:999'];
        $rules['max_meeting_permissions_per_team'] = ['required', 'integer', 'min:0', 'max:999'];

        return $rules;
    }
}
