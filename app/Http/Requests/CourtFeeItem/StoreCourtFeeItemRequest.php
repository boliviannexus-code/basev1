<?php

namespace App\Http\Requests\CourtFeeItem;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourtFeeItemRequest extends FormRequest
{
    public function rules(): array
    {
        $courtFee = $this->route('courtFee');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('court_fee_items')->where('court_fee_id', $courtFee?->id)],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    public function authorize(): bool
    {
        $courtFee = $this->route('courtFee');
        return ($this->user()?->can('court-fee-items.create') ?? false)
            && $courtFee && CompanyContext::belongsToUser($courtFee->company_id, $this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }
}
