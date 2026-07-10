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
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
                Rule::unique('tournaments', 'division_id')
                    ->where('company_id', $companyId)
                    ->where('season_id', $this->integer('season_id'))
                    ->whereNull('deleted_at'),
            ],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('division_categories', 'id')
                    ->where('company_id', $companyId)
                    ->where('division_id', $this->integer('division_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('tournaments')
                    ->where('company_id', $companyId)
                    ->where('season_id', $this->integer('season_id'))
                    ->where('division_id', $this->integer('division_id'))
                    ->whereNull('deleted_at'),
            ],
            'status' => ['required', Rule::in(['planned', 'active', 'closed'])],
        ];
    }

    public function messages(): array
    {
        return [
            'division_id.unique' => 'Ya existe un torneo registrado para esta division en la gestion seleccionada.',
        ];
    }
}
