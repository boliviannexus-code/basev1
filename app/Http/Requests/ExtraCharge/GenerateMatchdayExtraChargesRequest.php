<?php

namespace App\Http\Requests\ExtraCharge;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateMatchdayExtraChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $matchday = $this->route('matchday');

        return ($this->user()?->can('court-fee-items.update') ?? false)
            && $matchday
            && CompanyContext::belongsToUser($matchday->company_id, $this->user());
    }

    public function rules(): array
    {
        $matchday = $this->route('matchday');

        return [
            'extra_charge_ids' => ['nullable', 'array'],
            'extra_charge_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('extra_charges', 'id')->where(fn ($query) => $query
                    ->where('company_id', $matchday?->company_id)
                    ->where('is_active', true)),
            ],
        ];
    }
}
