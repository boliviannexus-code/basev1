<?php

namespace App\Http\Requests\CourtFee;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourtFeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fee = $this->route('courtFee');
        return ($this->user()?->can('court-fee-items.update') ?? false) && $fee && CompanyContext::belongsToUser($fee->company_id, $this->user());
    }

    public function rules(): array
    {
        $fee = $this->route('courtFee');
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('court_fees')->where('company_id', $fee?->company_id)->ignore($fee)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
