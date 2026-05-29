<?php

namespace App\Http\Requests\Division;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $division = $this->route('division');

        return ($this->user()?->can('divisions.update') ?? false)
            && CompanyContext::canOperate($this->user())
            && $division !== null
            && CompanyContext::belongsToUser($division->company_id, $this->user());
    }

    public function rules(): array
    {
        $division = $this->route('division');
        $companyId = $division?->company_id;

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueDivisionName($companyId, $division?->id)],
            'min_age' => ['required', 'integer', 'min:0', 'max:120'],
            'max_age' => ['required', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sync_categories' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'distinct', Rule::exists('division_categories', 'id')->where('company_id', $companyId)->where('is_active', true)->whereNull('deleted_at')],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    private function uniqueDivisionName(?int $companyId, ?int $divisionId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $divisionId): void {
            if (! $companyId || ! $divisionId || trim((string) $value) === '') {
                return;
            }

            $exists = DB::table('divisions')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('id', '!=', $divisionId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail('Ya existe una division con este nombre.');
            }
        };
    }
}
