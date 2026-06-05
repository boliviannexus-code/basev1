<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Reservation;
use App\Models\Space;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        $company = Company::factory()->create();
        $space = Space::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
        ]);
        $checkIn = now()->addDays(7);
        $checkOut = $checkIn->copy()->addDays(2);
        $nights = $checkIn->diffInDays($checkOut);
        $guests = fake()->numberBetween(1, 4);
        $nightlyPrice = fake()->randomFloat(2, 60, 300);
        $total = round($nightlyPrice * $nights, 2);
        $advance = round($total * 0.5, 2);

        return [
            'company_id' => $company->id,
            'user_id' => User::factory()->create(['company_id' => null])->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'occupancy_block_id' => null,
            'code' => 'RSV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'guest_phone' => fake()->phoneNumber(),
            'guest_country' => 'Bolivia',
            'guest_document' => fake()->numerify('########'),
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => $nights,
            'guests' => $guests,
            'price_per_person' => $nightlyPrice,
            'subtotal_amount' => $total,
            'total_amount' => $total,
            'advance_amount' => $advance,
            'balance_amount' => round($total - $advance, 2),
            'currency' => 'BOB',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => 'qr',
            'payment_reference' => null,
            'payment_proof_path' => null,
            'hold_expires_at' => now()->addMinutes((int) config('reservations.temporary_hold_minutes', 60)),
            'guest_notes' => null,
            'payment_validated_at' => null,
            'payment_validated_by' => null,
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'confirmed',
            'payment_status' => 'validated',
            'payment_validated_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'cancelled',
        ]);
    }
}
