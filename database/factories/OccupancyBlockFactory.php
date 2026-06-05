<?php

namespace Database\Factories;

use App\Models\OccupancyBlock;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OccupancyBlock>
 */
class OccupancyBlockFactory extends Factory
{
    public function definition(): array
    {
        $space = Space::factory()->create();

        return [
            'company_id' => $space->company_id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'type' => 'manual_block',
            'status' => 'active',
            'title' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ];
    }
}
