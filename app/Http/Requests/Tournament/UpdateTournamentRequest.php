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
            && CompanyContext::belongsToUser($tournament->company_id, $this->user());
    }

    public function rules(): array
    {
        $tournament = $this->route('tournament');
        $companyId = $tournament?->company_id;
        $currentCategoryId = $tournament?->category_id;

        return [
            'season_id' => ['required', 'integer', Rule::exists('seasons', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'division_id' => ['required', 'integer', Rule::exists('divisions', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('division_categories', 'id')
                    ->where(function ($query) use ($companyId, $currentCategoryId): void {
                        $query->where('company_id', $companyId)
                            ->where('division_id', $this->integer('division_id'))
                            ->whereNull('deleted_at')
                            ->where(function ($query) use ($currentCategoryId): void {
                                $query->where('is_active', true)
                                    ->when($currentCategoryId, fn ($query, $categoryId) => $query->orWhere('id', $categoryId));
                            });
                    }),
                Rule::unique('tournaments')
                    ->where('company_id', $companyId)
                    ->where('season_id', $this->integer('season_id'))
                    ->whereNull('deleted_at')
                    ->ignore($tournament?->id),
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
