<?php

namespace App\Http\Requests\Season;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return ($this->user()?->can('seasons.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $season !== null
            && CompanyContext::belongsToUser($season->company_id, $this->user());
    }

    public function rules(): array
    {
        $season = $this->route('season');
        $companyId = $season?->company_id ?? CompanyContext::id($this->user());

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('seasons')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($season?->id)],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
