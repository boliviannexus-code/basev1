<?php

namespace Tests\Unit;

use App\Models\Stay;
use App\Services\Occupancy\RoomCleaningService;
use Tests\TestCase;

class RoomCleaningResourceTest extends TestCase
{
    public function test_dirty_bed_blocks_itself_and_full_room_but_not_other_beds(): void
    {
        $task = new Stay([
            'space_id' => 1, 'space_room_id' => 2, 'room_bed_unit_id' => 3,
            'status' => 'checked_out', 'cleaning_required_at' => now(),
        ]);
        $tasks = collect([$task]);
        $this->assertSame($task, RoomCleaningService::forResource($tasks, 1, 2, 3));
        $this->assertSame($task, RoomCleaningService::forResource($tasks, 1, 2, null));
        $this->assertNull(RoomCleaningService::forResource($tasks, 1, 2, 4));
        $this->assertNull(RoomCleaningService::forResource($tasks, 1, 5, 3));
        $this->assertNull(RoomCleaningService::forResource($tasks, 6, 2, 3));

        $task->room_bed_unit_id = null;
        $this->assertSame($task, RoomCleaningService::forResource($tasks, 1, 2, 4));
        $task->cleaned_at = now();
        $this->assertNull(RoomCleaningService::forResource($tasks, 1, 2, 4));
    }
}
