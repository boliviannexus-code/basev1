<?php

namespace App\Http\Requests\Countries;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('countries.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('is_active')) {
            $data['is_active'] = $this->boolean('is_active');
        }

        if ($this->has('is_featured')) {
            $data['is_featured'] = $this->boolean('is_featured');
        }

        if ($this->has('sort_order')) {
            $data['sort_order'] = $this->filled('sort_order') ? (int) $this->input('sort_order') : 0;
        }

        $this->merge($data);
    }
}
