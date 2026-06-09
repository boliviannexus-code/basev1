<?php

namespace App\Http\Requests\CheckIns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'total_people' => ['nullable', 'integer', 'min:1'],
            'stays' => ['nullable', 'array'],
            'stays.*.check_out_date' => ['required_with:stays', 'date'],
            'stays.*.people_count' => ['required_with:stays', 'integer', 'min:1'],
            'stays.*.price_per_night_bob' => ['nullable', 'numeric', 'min:0'],
            'stays.*.price_per_night_usd' => ['nullable', 'numeric', 'min:0'],
            'stays.*.exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'stays.*.currency' => ['required_with:stays', Rule::in(['BOB'])],
            'stays.*.breakfast_included' => ['sometimes', 'boolean'],
            'stays.*.night_prices' => ['nullable', 'array'],
            'stays.*.night_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $group = $this->route('checkInGroup');
            $stays = $group?->stays?->keyBy('id') ?? collect();
            $today = now()->toDateString();

            foreach ($this->input('stays', []) as $stayId => $stayData) {
                $stay = $stays->get((int) $stayId);
                $checkIn = $stay?->check_in_date?->toDateString();
                $checkOut = $stayData['check_out_date'] ?? $stay?->check_out_date?->toDateString();

                foreach ($stayData['night_prices'] ?? [] as $date => $price) {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                        $validator->errors()->add("stays.{$stayId}.night_prices.{$date}", 'Fecha de noche invalida.');
                        continue;
                    }

                    if ($date < $today) {
                        $validator->errors()->add("stays.{$stayId}.night_prices.{$date}", 'No se puede modificar el precio de una noche pasada.');
                    }

                    if (($checkIn && $date < $checkIn) || ($checkOut && $date >= $checkOut)) {
                        $validator->errors()->add("stays.{$stayId}.night_prices.{$date}", 'La fecha no pertenece al rango de la estancia.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'total_people' => $this->filled('total_people') ? (int) $this->input('total_people') : null,
            'stays' => collect($this->input('stays', []))
                ->map(fn (array $stay): array => [
                    ...$stay,
                    'currency' => 'BOB',
                    'people_count' => filled($stay['people_count'] ?? null) ? (int) $stay['people_count'] : null,
                    'breakfast_included' => filter_var($stay['breakfast_included'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    'night_prices' => collect($stay['night_prices'] ?? [])
                        ->filter(fn ($value, $date): bool => filled($date) && filled($value))
                        ->mapWithKeys(fn ($value, $date): array => [(string) $date => round((float) $value, 2)])
                        ->all(),
                ])
                ->all(),
        ]);
    }
}
