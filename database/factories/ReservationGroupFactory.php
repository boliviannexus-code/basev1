<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\ReservationGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationGroup>
 */
class ReservationGroupFactory extends Factory
{
    protected $model = ReservationGroup::class;

    public function definition(): array
    {
        $company = Company::factory()->create();
        $checkIn = now()->addDays(7);
        $checkOut = $checkIn->copy()->addDays(2);
        $nights = $checkIn->diffInDays($checkOut);
        $total = 200.0 * $nights;

        return [
            'company_id' => $company->id,
            'reservation_channel_id' => null,
            'code' => ReservationGroup::nextCode($company->id),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'guest_document' => fake()->numerify('########'),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'guests' => 2,
            'subtotal_amount' => $total,
            'total_amount' => $total,
            'advance_amount' => 0,
            'balance_amount' => $total,
            'currency' => 'BOB',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => null,
            'payment_reference' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
