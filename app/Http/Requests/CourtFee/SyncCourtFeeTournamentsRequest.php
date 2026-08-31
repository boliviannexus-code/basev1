<?php

namespace App\Http\Requests\CourtFee;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncCourtFeeTournamentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fee = $this->route('courtFee');
        return ($this->user()?->can('court-fee-items.update') ?? false)
            && $fee && CompanyContext::belongsToUser($fee->company_id, $this->user());
    }

    public function rules(): array
    {
        $fee = $this->route('courtFee');
        return [
            'tournament_ids' => ['nullable', 'array'],
            'tournament_ids.*' => ['integer', 'distinct', Rule::exists('tournaments', 'id')->where(fn ($query) => $query->where('company_id', $fee?->company_id)->whereNull('deleted_at'))],
        ];
    }
}
