<?php

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReservationGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservations.manage') === true
            || $this->user()?->can('occupancy.manage') === true;
    }

    public function rules(): array
    {
        return [
            'reservations' => ['required', 'array', 'min:1'],
            'reservations.*.check_out' => ['required', 'date'],
            'reservations.*.price_per_night' => ['required', 'numeric', 'min:0'],
            'reservations.*.night_prices' => ['nullable', 'array'],
            'reservations.*.night_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reservations.*.check_out' => 'fecha de salida',
            'reservations.*.price_per_night' => 'precio por noche',
            'reservations.*.night_prices.*' => 'precio de noche',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $group = $this->route('group');
            $reservations = $group?->reservations()->get()->keyBy('id') ?? collect();

            foreach ($this->input('reservations', []) as $reservationId => $reservationData) {
                $reservation = $reservations->get((int) $reservationId);

                if (! $reservation) {
                    $validator->errors()->add("reservations.{$reservationId}", 'La reserva seleccionada no pertenece al grupo.');
                    continue;
                }

                if ((string) $reservationData['check_out'] <= $reservation->check_in->toDateString()) {
                    $validator->errors()->add("reservations.{$reservationId}.check_out", 'La fecha de salida debe ser posterior a la fecha de ingreso.');
                }

                foreach ($reservationData['night_prices'] ?? [] as $date => $price) {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                        $validator->errors()->add("reservations.{$reservationId}.night_prices.{$date}", 'Fecha de noche invalida.');
                        continue;
                    }

                    if ($date < $reservation->check_in->toDateString() || $date >= (string) $reservationData['check_out']) {
                        $validator->errors()->add("reservations.{$reservationId}.night_prices.{$date}", 'La fecha no pertenece al rango de la reserva.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reservations' => collect($this->input('reservations', []))
                ->map(function ($reservation): array {
                    $reservation = is_array($reservation) ? $reservation : [];

                    return [
                        'check_out' => $reservation['check_out'] ?? null,
                        'price_per_night' => filled($reservation['price_per_night'] ?? null)
                            ? round((float) $reservation['price_per_night'], 2)
                            : null,
                        'night_prices' => collect($reservation['night_prices'] ?? [])
                            ->filter(fn ($value, $date): bool => filled($date) && filled($value))
                            ->mapWithKeys(fn ($value, $date): array => [(string) $date => round((float) $value, 2)])
                            ->all(),
                    ];
                })
                ->all(),
        ]);
    }
}
