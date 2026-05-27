<?php

namespace App\Http\Requests\Division;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $division = $this->route('division');

        return ($this->user()?->can('divisions.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $division !== null
            && CompanyContext::belongsToUser($division->company_id, $this->user());
    }

    public function rules(): array
    {
        $division = $this->route('division');
        $companyId = $division?->company_id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('divisions')->where('company_id', $division?->company_id)->whereNull('deleted_at')->ignore($division?->id)],
            'min_age' => ['required', 'integer', 'min:0', 'max:120'],
            'max_age' => ['required', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sync_categories' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('division_categories', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
