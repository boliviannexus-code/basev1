<?php

namespace App\Http\Requests;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('companies.create') ?? false)
            && CompanyContext::isGlobalAdmin($this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::unique('companies', 'code')],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'foundation_date' => ['nullable', 'date'],
            'legal_personality' => ['nullable', 'string', 'max:255'],
            'interest_data' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'report_footer' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => is_string($this->input('code')) ? str($this->input('code'))->squish()->upper()->toString() : $this->input('code'),
        ]);
    }
}
