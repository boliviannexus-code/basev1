<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('categories.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
