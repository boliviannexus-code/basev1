<?php

namespace App\Http\Requests\PlayerTransfer;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerTransferSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('player-transfers.settings') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        return [
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'fee_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'next_sequence' => ['nullable', 'integer', 'min:1', 'max:999999999'],
        ];
    }
}
