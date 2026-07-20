<?php

namespace App\Http\Requests\Court;

use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateCourtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $court = $this->route('court');

        return ($this->user()?->can('courts.update') ?? false)
            && $court !== null
            && CompanyContext::belongsToUser($court->company_id, $this->user());
    }

    public function rules(): array
    {
        $court = $this->route('court');

        return [
            'name' => ['required', 'string', 'max:255', $this->uniqueCourtName($court?->company_id, $court?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
        ]);
    }

    private function uniqueCourtName(?int $companyId, ?int $courtId): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($companyId, $courtId): void {
            if (! $companyId || ! $courtId || trim((string) $value) === '') {
                return;
            }

            $exists = DB::table('courts')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('id', '!=', $courtId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $value))])
                ->exists();

            if ($exists) {
                $fail('Ya existe una cancha con este nombre.');
            }
        };
    }
}
