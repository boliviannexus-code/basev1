<?php

namespace App\Http\Requests\ExtraCharge;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExtraChargeRequest extends FormRequest
{
    public function authorize(): bool { return ($this->user()?->can('court-fee-items.create') ?? false) && CompanyContext::canOperate($this->user()); }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('extra_charges')->where('company_id', CompanyContext::id($this->user()))],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_per_team' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'installments' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
