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
            'name' => ['required', 'string', 'max:255'],
            'year' => [
                'required',
                'integer',
                'between:1900,2100',
                Rule::unique('seasons', 'year')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at')
                    ->ignore($season?->id),
            ],
            'status' => ['required', Rule::in(['active', 'closed'])],
        ];
    }
}
