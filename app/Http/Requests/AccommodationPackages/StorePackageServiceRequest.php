<?php

namespace App\Http\Requests\AccommodationPackages;

use App\Models\PackageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePackageServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('spaces.edit') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['nullable', Rule::in(PackageService::TYPES)],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->filled('name') ? trim((string) $this->input('name')) : null,
            'description' => $this->filled('description') ? trim((string) $this->input('description')) : null,
            'type' => $this->filled('type') ? trim((string) $this->input('type')) : null,
            'icon' => $this->filled('icon') ? trim((string) $this->input('icon')) : null,
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 0,
        ]);
    }
}
