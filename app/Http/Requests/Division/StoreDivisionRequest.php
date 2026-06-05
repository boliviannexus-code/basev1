<?php

namespace App\Http\Requests\Division;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreDivisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->can('divisions.create') ?? false) && CompanyContext::canOperate($this->user());
    }

    public function rules(): array
    {
        $companyId = CompanyContext::id($this->user()) ?? $this->integer('company_id');

        return [
            'company_id' => [CompanyContext::id($this->user()) === null ? 'required' : 'nullable', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255', $this->uniqueDivisionName($companyId)],
            'min_age' => ['required', 'integer', 'min:0', 'max:120'],
            'max_age' => ['required', 'integer', 'min:0', 'max:120', 'gte:min_age'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('name')) {
            $this->merge(['name' => trim((string) $this->input('name'))]);
        }
    }

    private function uniqueDivisionName(?int $companyId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($companyId): void {
            if (! $companyId || trim((string) $value) === '') {
                return;
            }

            $exists = DB::table('divisions')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail('Ya existe una division con este nombre.');
            }
        };
    }
}
