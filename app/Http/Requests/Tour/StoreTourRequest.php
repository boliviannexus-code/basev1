<?php

namespace App\Http\Requests\Tour;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tours.create') ?? false;
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user());

        $targetCompanyId = $companyId ?? $this->integer('company_id');

        return [
            'company_id' => [
                Rule::requiredIf($companyId === null),
                'nullable',
                'integer',
                Rule::exists('companies', 'id')->where('is_active', true),
            ],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:255', Rule::unique('tours', 'title')->where('company_id', $targetCompanyId)],
            'reference_code' => ['nullable', 'string', 'max:120', Rule::unique('tours', 'reference_code')->where('company_id', $targetCompanyId)],
        ];
    }
}
