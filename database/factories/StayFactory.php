<?php

namespace Database\Factories;

use App\Models\CheckInGroup;
use App\Models\Guest;
use App\Models\Space;
use App\Models\Stay;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stay>
 */
class StayFactory extends Factory
{
    public function definition(): array
    {
        $group = CheckInGroup::factory()->create();
        $space = Space::factory()->create(['company_id' => $group->company_id]);
        $holder = Guest::factory()->create(['company_id' => $group->company_id]);

        return [
            'company_id' => $group->company_id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $holder->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'room_bed_unit_id' => null,
            'people_count' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'nights' => 1,
            'price_per_night_bob' => 100,
            'price_per_night_usd' => null,
            'exchange_rate' => 1,
            'currency' => 'BOB',
            'breakfast_included' => false,
            'status' => 'occupied',
            'notes' => null,
        ];
    }
}
