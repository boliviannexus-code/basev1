<?php

namespace App\Http\Requests\ActivityType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateActivityTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('activity_types.update') ?? false;
    }

    public function rules(): array
    {
        $activityType = $this->route('activityType');

        return [
            'title' => ['required', 'string', 'max:255', Rule::unique('activity_types', 'title')->ignore($activityType)],
            'slug' => ['required', 'string', 'max:255', Rule::unique('activity_types', 'slug')->ignore($activityType)],
            'icon' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => Str::slug($this->input('slug') ?: $this->input('title')),
        ]);
    }
}
