<?php

namespace App\Http\Requests\FingerprintTemplate;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateFingerprintTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fingerprintTemplate = $this->route('fingerprintTemplate');

        return ($this->user()?->can('fingerprint-templates.update') ?? false)
            && $fingerprintTemplate !== null
            && CompanyContext::belongsToUser($fingerprintTemplate->user?->company_id, $this->user());
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'string', 'max:100'],
            'template_data' => ['required', 'string', 'min:20'],
        ];
    }
}
