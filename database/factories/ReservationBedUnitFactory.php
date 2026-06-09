<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\ReservationBedUnit;
use App\Models\RoomBedUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReservationBedUnit>
 */
class ReservationBedUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reservation_id' => Reservation::factory()->create()->id,
            'room_bed_unit_id' => RoomBedUnit::factory()->create()->id,
            'occupancy_block_id' => null,
            'guest_name' => fake()->name(),
            'price_per_night' => fake()->randomFloat(2, 20, 120),
            'subtotal_amount' => fake()->randomFloat(2, 40, 300),
        ];
    }
}
