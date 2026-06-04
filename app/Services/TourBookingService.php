<?php

namespace App\Services;

use App\Models\Tour;
use App\Models\TourAvailability;
use App\Models\TourBooking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TourBookingService
{
    public function quote(Tour $tour, string $date, int $people): array
    {
        $availability = $tour->availabilities()
            ->whereDate('date', $date)
            ->with('prices')
            ->first();

        $this->validateAvailability($tour, $availability, $people);

        $unitPrice = $this->unitPrice($tour, $availability, $people);

        return [
            'availability' => $availability,
            'unit_price' => $unitPrice,
            'total' => $unitPrice * $people,
        ];
    }

    public function create(User $user, Tour $tour, array $data): TourBooking
    {
        return DB::transaction(function () use ($user, $tour, $data): TourBooking {
            $availability = $tour->availabilities()
                ->whereDate('date', $data['travel_date'])
                ->lockForUpdate()
                ->with('prices')
                ->first();

            $people = (int) $data['people'];
            $this->validateAvailability($tour, $availability, $people);
            $unitPrice = $this->unitPrice($tour, $availability, $people);

            $booking = TourBooking::query()->create([
                'user_id' => $user->id,
                'tour_id' => $tour->id,
                'tour_availability_id' => $availability?->id,
                'booking_code' => $this->bookingCode(),
                'travel_date' => $data['travel_date'],
                'people' => $people,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'country' => $data['country'],
                'special_requirements' => $data['special_requirements'] ?? null,
                'unit_price_usd' => $unitPrice,
                'total_usd' => $unitPrice * $people,
                'status' => TourBooking::STATUS_CONFIRMED,
            ]);

            $availability?->increment('booked_count', $people);

            return $booking->load(['tour.images', 'tour.category', 'availability']);
        });
    }

    private function validateAvailability(Tour $tour, ?TourAvailability $availability, int $people): void
    {
        if ($tour->status !== Tour::STATUS_ACTIVE || $tour->review_status !== Tour::REVIEW_APPROVED || ! $tour->bookings_enabled) {
            throw ValidationException::withMessages(['travel_date' => 'Este tour no esta disponible para reservas.']);
        }

        if (! $availability || $availability->status !== TourAvailability::STATUS_AVAILABLE) {
            throw ValidationException::withMessages(['travel_date' => 'La fecha seleccionada no esta disponible.']);
        }

        $capacity = $availability->capacity ?? $tour->capacity;

        if ($capacity !== null && ($capacity - $availability->booked_count) < $people) {
            throw ValidationException::withMessages(['people' => 'No hay cupos suficientes para la cantidad seleccionada.']);
        }
    }

    private function unitPrice(Tour $tour, TourAvailability $availability, int $people): float
    {
        $price = $this->selectPriceForPeople($availability->prices, $people);

        if (! $price) {
            $price = $this->selectPriceForPeople($tour->prices()->get(), $people);
        }

        if (! $price) {
            throw ValidationException::withMessages(['people' => 'No hay una tarifa configurada para esta cantidad de personas.']);
        }

        return (float) $price->price_usd;
    }

    private function selectPriceForPeople(Collection $prices, int $people): mixed
    {
        $applicable = $prices
            ->filter(fn ($price): bool => $price->min_people <= $people && ($price->max_people === null || $price->max_people >= $people))
            ->sort($this->priceSorter());

        if ($applicable->isNotEmpty()) {
            return $applicable->first();
        }

        return $prices
            ->filter(fn ($price): bool => (int) $price->min_people === 1)
            ->sort($this->priceSorter())
            ->first();
    }

    private function priceSorter(): callable
    {
        return function ($first, $second): int {
            if ((int) $first->min_people !== (int) $second->min_people) {
                return (int) $second->min_people <=> (int) $first->min_people;
            }

            $firstMax = $first->max_people === null ? PHP_INT_MAX : (int) $first->max_people;
            $secondMax = $second->max_people === null ? PHP_INT_MAX : (int) $second->max_people;

            return $firstMax <=> $secondMax;
        };
    }

    private function bookingCode(): string
    {
        do {
            $code = 'TRV-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (TourBooking::query()->where('booking_code', $code)->exists());

        return $code;
    }
}
