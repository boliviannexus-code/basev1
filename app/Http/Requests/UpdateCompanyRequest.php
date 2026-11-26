<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return ($this->user()?->can('companies.update') ?? false)
            && $company instanceof Company
            && CompanyContext::belongsToUser($company->id, $this->user());
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'local_currency' => ['sometimes', 'required', \Illuminate\Validation\Rule::in(array_keys(\App\Support\CompanyCurrency::CURRENCIES))],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_id' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'report_footer' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
    public function after(): array
    {
        return [function (\Illuminate\Validation\Validator $validator): void {
            $company = $this->route('company');
            if ($this->has('local_currency') && $company instanceof Company
                && $this->input('local_currency') !== ($company->local_currency ?? 'BOB')
                && ! $this->user()?->hasRole('super_admin')) {
                $validator->errors()->add('local_currency', 'Solo un superadministrador puede cambiar la moneda.');
            }
        }];
    }

}
