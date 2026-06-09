<?php

namespace Database\Factories;

use App\Models\BedType;
use App\Models\RoomBedUnit;
use App\Models\SpaceRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBedUnit>
 */
class RoomBedUnitFactory extends Factory
{
    public function definition(): array
    {
        $room = SpaceRoom::factory()->create();

        return [
            'company_id' => $room->company_id,
            'space_room_id' => $room->id,
            'room_bed_id' => null,
            'bed_type_id' => BedType::query()->firstOrCreate(
                ['slug' => 'cama-individual'],
                ['name' => 'Cama individual', 'capacity' => 1, 'is_active' => true],
            )->id,
            'label' => 'Cama '.fake()->unique()->bothify('??'),
            'code' => fake()->unique()->bothify('BED-###'),
            'sort_order' => fake()->numberBetween(1, 20),
            'status' => 'active',
        ];
    }
}
