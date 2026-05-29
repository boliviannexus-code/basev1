<?php

namespace App\Http\Requests\GuideType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuideTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guide_types.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('guide_types', 'title')],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
