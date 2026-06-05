<?php

namespace Database\Factories;

use App\Models\AvailabilityDay;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityDay>
 */
class AvailabilityDayFactory extends Factory
{
    public function definition(): array
    {
        $space = Space::factory()->create();

        return [
            'company_id' => $space->company_id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => fake()->date(),
            'price' => fake()->randomFloat(2, 40, 400),
            'status' => 'available',
        ];
    }
}
