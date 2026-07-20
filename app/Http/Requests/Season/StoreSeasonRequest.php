<?php

namespace App\Http\Requests\Season;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('seasons.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');

        return [
            'company_id' => [CompanyContext::id($this->user()) === null ? 'required' : 'nullable', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'year' => [
                'required',
                'integer',
                'between:1900,2100',
                Rule::unique('seasons', 'year')
                    ->where('company_id', $companyId)
                    ->whereNull('deleted_at'),
            ],
            'status' => ['sometimes', Rule::in(['active', 'closed'])],
        ];
    }
}
