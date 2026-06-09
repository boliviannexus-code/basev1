<?php

namespace App\Http\Requests\CheckIns;

use App\Models\Country;
use App\Models\Guest;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateStayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('occupancy.manage') === true
            && $this->user()?->company_id !== null;
    }

    public function rules(): array
    {
        $companyId = (int) $this->user()?->company_id;

        return [
            'check_out_date' => ['required', 'date'],
            'people_count' => ['required', 'integer', 'min:1'],
            'price_per_night_bob' => ['nullable', 'numeric', 'min:0'],
            'price_per_night_usd' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'currency' => ['required', Rule::in(['BOB'])],
            'breakfast_included' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'night_prices' => ['nullable', 'array'],
            'night_prices.*' => ['nullable', 'numeric', 'min:0'],
            'guests' => ['nullable', 'array'],
            'guests.*.id' => [
                'nullable',
                'integer',
                Rule::exists((new Guest)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
            'guests.*.document_type' => ['required_with:guests', Rule::in(['passport', 'dni', 'ci', 'other'])],
            'guests.*.document_number' => ['nullable', 'string', 'max:80'],
            'guests.*.first_name' => ['required_with:guests', 'string', 'max:120'],
            'guests.*.last_name' => ['required_with:guests', 'string', 'max:120'],
            'guests.*.birth_date' => ['required_with:guests', 'date', 'before_or_equal:today'],
            'guests.*.birth_country_id' => [
                'required_with:guests',
                Rule::exists((new Country)->getTable(), 'id')->where(fn (QueryBuilder $query): QueryBuilder => $query->where('company_id', $companyId)),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'guests.*.document_type' => 'tipo de documento del huesped',
            'guests.*.document_number' => 'numero de documento del huesped',
            'guests.*.first_name' => 'nombre del huesped',
            'guests.*.last_name' => 'apellido paterno del huesped',
            'guests.*.birth_date' => 'fecha de nacimiento del huesped',
            'guests.*.birth_country_id' => 'pais de nacimiento del huesped',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $peopleCount = (int) $this->input('people_count', 0);
            $additionalGuests = count($this->input('guests', []));

            if ($additionalGuests > max($peopleCount - 1, 0)) {
                $validator->errors()->add('guests', 'La cantidad de acompañantes no puede superar las personas de la estancia. El titular ya esta incluido.');
            }

            foreach ($this->input('guests', []) as $guestIndex => $guest) {
                foreach (['document_type', 'first_name', 'last_name', 'birth_date', 'birth_country_id'] as $field) {
                    if (! filled($guest[$field] ?? null)) {
                        $validator->errors()->add("guests.{$guestIndex}.{$field}", 'Completa este dato del huesped.');
                    }
                }
            }

            $today = now()->toDateString();
            $stay = $this->route('stay');
            $checkIn = $stay?->check_in_date?->toDateString();
            $checkOut = $this->filled('check_out_date')
                ? (string) $this->input('check_out_date')
                : $stay?->check_out_date?->toDateString();

            foreach ($this->input('night_prices', []) as $date => $price) {
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                    $validator->errors()->add("night_prices.{$date}", 'Fecha de noche invalida.');
                    continue;
                }

                if ($date < $today) {
                    $validator->errors()->add("night_prices.{$date}", 'No se puede modificar el precio de una noche pasada.');
                }

                if (($checkIn && $date < $checkIn) || ($checkOut && $date >= $checkOut)) {
                    $validator->errors()->add("night_prices.{$date}", 'La fecha no pertenece al rango de la estancia.');
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency' => 'BOB',
            'people_count' => $this->filled('people_count') ? (int) $this->input('people_count') : null,
            'breakfast_included' => $this->boolean('breakfast_included'),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'night_prices' => collect($this->input('night_prices', []))
                ->filter(fn ($value, $date): bool => filled($date) && filled($value))
                ->mapWithKeys(fn ($value, $date): array => [(string) $date => round((float) $value, 2)])
                ->all(),
            'guests' => collect($this->input('guests', []))
                ->filter(fn (array $guest): bool => filled($guest['id'] ?? null)
                    || filled($guest['first_name'] ?? null)
                    || filled($guest['last_name'] ?? null)
                    || filled($guest['birth_date'] ?? null)
                    || filled($guest['birth_country_id'] ?? null)
                    || filled($guest['document_type'] ?? null)
                    || filled($guest['document_number'] ?? null))
                ->map(fn (array $guest): array => [
                    'id' => filled($guest['id'] ?? null) ? (int) $guest['id'] : null,
                    'document_type' => filled($guest['document_type'] ?? null) ? trim((string) $guest['document_type']) : 'passport',
                    'document_number' => filled($guest['document_number'] ?? null) ? trim((string) $guest['document_number']) : null,
                    'first_name' => filled($guest['first_name'] ?? null) ? trim((string) $guest['first_name']) : null,
                    'last_name' => filled($guest['last_name'] ?? null) ? trim((string) $guest['last_name']) : null,
                    'birth_date' => filled($guest['birth_date'] ?? null) ? $guest['birth_date'] : null,
                    'birth_country_id' => filled($guest['birth_country_id'] ?? null) ? (int) $guest['birth_country_id'] : null,
                ])
                ->values()
                ->all(),
        ]);
    }
}
