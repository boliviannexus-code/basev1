<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return ($this->user()?->can('companies.update') ?? false)
            && $company
            && CompanyContext::belongsToUser($company->id, $this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::unique('companies', 'code')->ignore($this->route('company'))],
            'subdomain' => ['nullable', 'string', 'max:63', 'regex:/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', Rule::unique('companies', 'subdomain')->ignore($this->route('company'))],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'foundation_date' => ['nullable', 'date'],
            'legal_personality' => ['nullable', 'string', 'max:255'],
            'interest_data' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'report_footer' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? str($this->input('code'))->squish()->upper()->toString() : $this->input('code'),
            'subdomain' => is_string($this->input('subdomain')) ? \App\Models\Company::normalizeSubdomain($this->input('subdomain')) : $this->input('subdomain'),
        ]);
    }
}
