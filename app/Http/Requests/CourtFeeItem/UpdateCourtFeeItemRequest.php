<?php

namespace App\Http\Requests\CourtFeeItem;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourtFeeItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $item = $this->route('courtFeeItem');
        $companyId = $item?->courtFee()->value('company_id');

        return ($this->user()?->can('court-fee-items.update') ?? false)
            && $companyId !== null
            && CompanyContext::belongsToUser((int) $companyId, $this->user());
    }

    public function rules(): array
    {
        $item = $this->route('courtFeeItem');

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('court_fee_items')->where('court_fee_id', $item?->court_fee_id)->ignore($item)],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);
    }
}
