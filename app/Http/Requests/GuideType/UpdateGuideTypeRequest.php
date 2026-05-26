<?php

namespace App\Http\Requests\GuideType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGuideTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('guide_types.update') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('guide_types', 'title')->ignore($this->route('guideType'))],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
