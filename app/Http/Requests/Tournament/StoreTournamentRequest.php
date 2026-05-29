<?php

namespace App\Http\Requests\Tournament;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('tournaments.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');

        return [
            'company_id' => [CompanyContext::id($this->user()) === null ? 'required' : 'nullable', 'exists:companies,id'],
            'season_id' => ['required', 'integer', Rule::exists('seasons', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('division_categories', 'id')
                    ->where('company_id', $companyId)
                    ->where('division_id', $this->integer('division_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
                Rule::unique('tournaments')
                    ->where('company_id', $companyId)
                    ->where('season_id', $this->integer('season_id'))
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
            ],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
