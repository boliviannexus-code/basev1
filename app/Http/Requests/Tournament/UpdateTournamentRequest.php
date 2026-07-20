<?php

namespace App\Http\Requests\Tournament;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTournamentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tournament = $this->route('tournament');

        return ($this->user()?->can('tournaments.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $tournament !== null
            && CompanyContext::belongsToUser($tournament->company_id, $this->user())
            && $tournament->status === 'planned';
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $companyId = $tournament?->company_id;

        return [
            'season_id' => ['required', 'integer', Rule::exists('seasons', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'division_id' => [
                'required',
                'integer',
                Rule::exists('divisions', 'id')->where('company_id', $companyId)->whereNull('deleted_at'),
                Rule::unique('tournaments', 'division_id')
                    ->where('company_id', $companyId)
                    ->where('season_id', $this->integer('season_id'))
                    ->whereNull('deleted_at')
                    ->ignore($tournament?->id),
            ],
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('division_categories', 'id')
                    ->where('company_id', $companyId)
                    ->where('division_id', $this->integer('division_id'))
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
                    ->whereNull('deleted_at')
                    ->ignore($tournament?->id),
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
