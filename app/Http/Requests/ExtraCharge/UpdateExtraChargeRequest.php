<?php

namespace App\Http\Requests\ExtraCharge;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExtraChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charge = $this->route('extraCharge');
        return ($this->user()?->can('court-fee-items.update') ?? false) && $charge && CompanyContext::belongsToUser($charge->company_id, $this->user());
    }
    public function rules(): array
    {
        $charge = $this->route('extraCharge');
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('extra_charges')->where('company_id', $charge?->company_id)->ignore($charge)],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount_per_team' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'installments' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
