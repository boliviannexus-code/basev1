<?php

namespace App\Http\Requests\Tour;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTourPricesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('tours.pricing') ?? false;
    }

    public function rules(): array
    {
        return [
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.title' => ['nullable', 'string', 'max:120'],
            'prices.*.min_people' => ['required', 'integer', 'min:1', 'max:9999'],
            'prices.*.max_people' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'prices.*.price_usd' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                foreach ($this->input('prices', []) as $index => $price) {
                    $min = (int) ($price['min_people'] ?? 0);
                    $max = $price['max_people'] ?? null;

                    if ($max !== null && $max !== '' && (int) $max < $min) {
                        $validator->errors()->add("prices.$index.max_people", 'El maximo de personas debe ser mayor o igual al minimo.');
                    }
                }
            },
        ];
    }
}
