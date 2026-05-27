<?php

namespace App\Http\Requests\DivisionCategory;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDivisionCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $category = $this->route('category');

        return ($this->user()?->can('categories.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $category !== null
            && CompanyContext::belongsToUser($category->company_id, $this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user());

        return [
            'division_id' => ['required', Rule::exists('divisions', 'id')->when($companyId, fn ($rule) => $rule->where('company_id', $companyId))->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
