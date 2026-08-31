<?php

namespace App\Http\Requests\ExtraCharge;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;

class SyncExtraChargeAssignmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $charge = $this->route('extraCharge');
        return ($this->user()?->can('court-fee-items.update') ?? false) && $charge && CompanyContext::belongsToUser($charge->company_id, $this->user());
    }
    public function rules(): array
    {
        return ['assignments' => ['nullable', 'array'], 'assignments.*.all' => ['nullable', 'boolean'], 'assignments.*.teams' => ['nullable', 'array'], 'assignments.*.teams.*' => ['integer']];
    }
}
