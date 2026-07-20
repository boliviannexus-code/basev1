<?php

namespace App\Http\Requests\Tour;

use Illuminate\Foundation\Http\FormRequest;

class RejectTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tours.review') ?? false;
    }

    public function rules(): array
    {
        return [
            'rejection_points' => ['required', 'array', 'min:1'],
            'rejection_points.*' => ['required', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'rejection_points' => collect($this->input('rejection_points', []))
                ->map(fn ($point): string => trim((string) $point))
                ->filter()
                ->values()
                ->all(),
        ]);
    }
}
