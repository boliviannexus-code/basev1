<?php

namespace App\Http\Requests\Tour;

use App\Services\TourService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTourRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tours.edit') ?? false;
    }

    public function rules(): array
    {
        $step = (int) $this->route('step');

        return app(TourService::class)->rulesForStep($step, $this->route('tour'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('keywords') && is_string($this->input('keywords'))) {
            $this->merge([
                'keywords' => collect(explode(',', (string) $this->input('keywords')))
                    ->map(fn (string $keyword): string => trim($keyword))
                    ->filter()
                    ->unique(fn (string $keyword): string => mb_strtolower($keyword))
                    ->values()
                    ->all(),
            ]);
        }
    }
}
