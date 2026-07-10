<?php

namespace App\Http\Requests\Reservations;

use App\Models\ReservationChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
        $companyId = (int) $this->user()?->company_id;

        return [
            'reservation_channel_id' => [
                'nullable',
                'integer',
                Rule::exists((new ReservationChannel)->getTable(), 'id')->where(fn ($query) => $query
                    ->where('company_id', $companyId)
                    ->where('is_active', true)),
            ],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:255'],
            'guest_document' => ['nullable', 'string', 'max:255'],
            'reservations' => ['required', 'array', 'min:1'],
            'reservations.*.check_in' => ['required', 'date', 'after_or_equal:today'],
            'reservations.*.check_out' => ['required', 'date'],
            'reservations.*.nights' => ['required', 'integer', 'min:1'],
            'reservations.*.price_per_night' => ['required', 'numeric', 'min:0'],
            'reservations.*.night_prices' => ['nullable', 'array'],
            'reservations.*.night_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reservation_channel_id' => 'canal de reserva',
            'guest_name' => 'huesped titular',
            'guest_email' => 'correo del huesped titular',
            'guest_phone' => 'telefono del huesped titular',
            'guest_document' => 'documento del huesped titular',
            'reservations.*.check_in' => 'fecha de ingreso',
            'reservations.*.check_out' => 'fecha de salida',
            'reservations.*.nights' => 'noches',
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

                if ((string) $reservationData['check_out'] <= (string) $reservationData['check_in']) {
                    $validator->errors()->add("reservations.{$reservationId}.check_out", 'La fecha de salida debe ser posterior a la fecha de ingreso.');
                }

                foreach ($reservationData['night_prices'] ?? [] as $date => $price) {
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date)) {
                        $validator->errors()->add("reservations.{$reservationId}.night_prices.{$date}", 'Fecha de noche invalida.');
                        continue;
                    }

                    if ($date < (string) $reservationData['check_in'] || $date >= (string) $reservationData['check_out']) {
                        $validator->errors()->add("reservations.{$reservationId}.night_prices.{$date}", 'La fecha no pertenece al rango de la reserva.');
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reservation_channel_id' => $this->filled('reservation_channel_id') ? (int) $this->input('reservation_channel_id') : null,
            'guest_name' => $this->filled('guest_name') ? trim((string) $this->input('guest_name')) : null,
            'guest_email' => $this->filled('guest_email') ? trim((string) $this->input('guest_email')) : null,
            'guest_phone' => $this->filled('guest_phone') ? trim((string) $this->input('guest_phone')) : null,
            'guest_document' => $this->filled('guest_document') ? trim((string) $this->input('guest_document')) : null,
            'reservations' => collect($this->input('reservations', []))
                ->map(function ($reservation): array {
                    $reservation = is_array($reservation) ? $reservation : [];

                    return [
                        'check_in' => $reservation['check_in'] ?? null,
                        'check_out' => $reservation['check_out'] ?? null,
                        'nights' => filled($reservation['nights'] ?? null)
                            ? max((int) $reservation['nights'], 1)
                            : null,
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
