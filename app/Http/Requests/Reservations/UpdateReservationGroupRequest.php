<?php

namespace App\Http\Requests\Reservations;

use App\Models\ReservationChannel;
use Carbon\CarbonImmutable;
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
            'check_in_type' => ['nullable', Rule::in(['individual', 'multiple'])],
            'document_type' => ['nullable', Rule::in(['passport', 'dni', 'ci', 'other'])],
            'birth_country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'birth_date' => ['nullable', 'date', 'before_or_equal:today'],
            'total_people' => ['nullable', 'integer', 'min:1'],
            'check_in_date' => ['nullable', 'date', 'after_or_equal:today'],
            'check_out_date' => ['nullable', 'date', 'after:check_in_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'guest_name' => ['required', 'string', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            'guest_email' => ['nullable', 'email', 'max:255'],
            'guest_phone' => ['nullable', 'string', 'max:255'],
            'guest_document' => ['nullable', 'string', 'max:255'],
            'reservations' => ['required', 'array', 'min:1'],
            'reservations.*.check_in' => ['required', 'date', 'after_or_equal:today'],
            'reservations.*.check_out' => ['required', 'date'],
            'reservations.*.nights' => ['required', 'integer', 'min:1'],
            'reservations.*.guests' => ['required', 'integer', 'min:1'],
            'reservations.*.price_per_night' => ['nullable', 'numeric', 'min:0'],
            'reservations.*.price_per_night_bob' => ['nullable', 'numeric', 'min:0'],
            'reservations.*.price_per_night_usd' => ['nullable', 'numeric', 'min:0'],
            'reservations.*.exchange_rate' => ['nullable', 'numeric', 'min:0.0001'],
            'reservations.*.currency' => ['nullable', Rule::in(['BOB'])],
            'reservations.*.night_prices' => ['nullable', 'array'],
            'reservations.*.night_prices.*' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reservation_channel_id' => 'canal de reserva',
            'guest_name' => 'huesped titular',
            'first_name' => 'nombre',
            'last_name' => 'apellido',
            'guest_email' => 'correo del huesped titular',
            'guest_phone' => 'telefono del huesped titular',
            'guest_document' => 'documento del huesped titular',
            'document_type' => 'tipo de documento',
            'birth_country_id' => 'pais de nacimiento',
            'birth_date' => 'fecha de nacimiento',
            'total_people' => 'cantidad total de personas',
            'check_in_date' => 'fecha de ingreso',
            'check_out_date' => 'fecha de salida',
            'notes' => 'notas',
            'reservations.*.check_in' => 'fecha de ingreso',
            'reservations.*.check_out' => 'fecha de salida',
            'reservations.*.nights' => 'noches',
            'reservations.*.guests' => 'personas',
            'reservations.*.price_per_night' => 'precio por noche',
            'reservations.*.price_per_night_bob' => 'precio por noche BOB',
            'reservations.*.price_per_night_usd' => 'precio por noche USD',
            'reservations.*.exchange_rate' => 'tipo de cambio',
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

                $hasBob = filled($reservationData['price_per_night_bob'] ?? null) || filled($reservationData['price_per_night'] ?? null);
                $hasUsd = filled($reservationData['price_per_night_usd'] ?? null);

                if (! $hasBob && ! $hasUsd) {
                    $validator->errors()->add("reservations.{$reservationId}.price_per_night_bob", 'Ingresa el precio por noche en BOB o USD.');
                }

                if ((! $hasBob || ! $hasUsd) && ! filled($reservationData['exchange_rate'] ?? null)) {
                    $validator->errors()->add("reservations.{$reservationId}.exchange_rate", 'Configura un tipo de cambio vigente para guardar BOB y USD.');
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
            'check_in_type' => $this->input('check_in_type'),
            'document_type' => $this->input('document_type', 'ci'),
            'birth_country_id' => $this->filled('birth_country_id') ? (int) $this->input('birth_country_id') : null,
            'birth_date' => $this->input('birth_date'),
            'total_people' => $this->filled('total_people') ? (int) $this->input('total_people') : null,
            'check_in_date' => $this->input('check_in_date'),
            'check_out_date' => $this->input('check_out_date'),
            'notes' => $this->filled('notes') ? trim((string) $this->input('notes')) : null,
            'first_name' => $this->filled('first_name') ? trim((string) $this->input('first_name')) : null,
            'last_name' => $this->filled('last_name') ? trim((string) $this->input('last_name')) : null,
            'guest_name' => $this->guestNameForValidation(),
            'guest_email' => $this->filled('guest_email') ? mb_strtolower(trim((string) $this->input('guest_email'))) : null,
            'guest_phone' => $this->filled('guest_phone') ? trim((string) $this->input('guest_phone')) : null,
            'guest_document' => $this->filled('guest_document') ? trim((string) $this->input('guest_document')) : null,
            'reservations' => collect($this->input('reservations', []))
                ->map(function ($reservation): array {
                    $reservation = is_array($reservation) ? $reservation : [];
                    $checkIn = $reservation['check_in'] ?? $this->input('check_in_date');
                    $checkOut = $reservation['check_out'] ?? $this->input('check_out_date');
                    $nights = $checkIn && $checkOut
                        ? $this->nightsBetween((string) $checkIn, (string) $checkOut)
                        : (filled($reservation['nights'] ?? null) ? max((int) $reservation['nights'], 1) : null);

                    return [
                        'check_in' => $checkIn,
                        'check_out' => $checkOut,
                        'nights' => $nights,
                        'guests' => filled($reservation['guests'] ?? null) ? max((int) $reservation['guests'], 1) : 1,
                        'price_per_night' => filled($reservation['price_per_night'] ?? null)
                            ? round((float) $reservation['price_per_night'], 2)
                            : null,
                        'price_per_night_bob' => filled($reservation['price_per_night_bob'] ?? null)
                            ? round((float) $reservation['price_per_night_bob'], 2)
                            : (filled($reservation['price_per_night'] ?? null) ? round((float) $reservation['price_per_night'], 2) : null),
                        'price_per_night_usd' => filled($reservation['price_per_night_usd'] ?? null)
                            ? round((float) $reservation['price_per_night_usd'], 2)
                            : null,
                        'exchange_rate' => filled($reservation['exchange_rate'] ?? null)
                            ? round((float) $reservation['exchange_rate'], 4)
                            : null,
                        'currency' => 'BOB',
                        'night_prices' => collect($reservation['night_prices'] ?? [])
                            ->filter(fn ($value, $date): bool => filled($date) && filled($value))
                            ->mapWithKeys(fn ($value, $date): array => [(string) $date => round((float) $value, 2)])
                            ->all(),
                    ];
                })
                ->all(),
        ]);
    }

    private function guestNameForValidation(): ?string
    {
        if ($this->filled('first_name') || $this->filled('last_name')) {
            return trim(collect([$this->input('first_name'), $this->input('last_name')])->filter()->implode(' '));
        }

        return $this->filled('guest_name') ? trim((string) $this->input('guest_name')) : null;
    }

    private function nightsBetween(string $checkIn, string $checkOut): int
    {
        try {
            return max(CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut)), 1);
        } catch (\Throwable) {
            return 1;
        }
    }
}
