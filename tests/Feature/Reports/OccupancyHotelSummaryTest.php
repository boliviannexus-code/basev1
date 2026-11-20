<?php

namespace Tests\Feature\Reports;

use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\Stay;
use App\Services\Reports\OperationalReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OccupancyHotelSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_sums_occupied_and_free_rooms_and_people_for_each_day(): void
    {
        $stay = Stay::factory()->create(['people_count' => 2, 'check_out_date' => now()->addDays(3)->toDateString()]);
        $space = Space::findOrFail($stay->space_id);
        $space->update(['status' => 'active', 'title' => 'Hotel prueba']);
        $occupiedRoom = SpaceRoom::factory()->create(['company_id' => $stay->company_id, 'space_id' => $space->id, 'status' => 'active']);
        SpaceRoom::factory()->create(['company_id' => $stay->company_id, 'space_id' => $space->id, 'status' => 'active']);
        $stay->update(['space_room_id' => $occupiedRoom->id]);
        $service = app(OperationalReportService::class);
        $filters = $service->filters(['from' => now()->toDateString(), 'to' => now()->addDays(2)->toDateString(), 'occupancy_status' => 'reserved']);

        $summary = $service->occupancy($stay->company_id, $filters)['hotel_summary']->first();

        $this->assertSame(3, $summary['occupied']);
        $this->assertSame(3, $summary['free']);
        $this->assertSame(6, $summary['people']);
        $this->assertEquals(50, $summary['rate']);
    }
    public function test_daily_report_uses_current_stays_breakfast_for_each_bed(): void
    {
        $stay = Stay::factory()->create(['people_count' => 1, 'breakfast_included' => true, 'check_in_date' => now()->subDay()->toDateString(), 'check_out_date' => now()->addDay()->toDateString()]);
        $space = Space::findOrFail($stay->space_id);
        $space->update(['status' => 'active']);
        $room = SpaceRoom::factory()->create(['company_id' => $stay->company_id, 'space_id' => $space->id, 'status' => 'active', 'sale_mode' => 'bed_unit']);
        $bed = \App\Models\RoomBedUnit::factory()->create(['company_id' => $stay->company_id, 'space_room_id' => $room->id, 'status' => 'active', 'label' => 'Cama 1']);
        $otherBed = \App\Models\RoomBedUnit::factory()->create(['company_id' => $stay->company_id, 'space_room_id' => $room->id, 'status' => 'active', 'label' => 'Cama 2']);
        $stay->update(['space_room_id' => $room->id, 'room_bed_unit_id' => $bed->id]);
        $service = app(OperationalReportService::class);
        $rows = $service->dailyOccupancy($stay->company_id, \Carbon\CarbonImmutable::today(), $service->filters([]))['rooms'];

        $this->assertSame(1, $rows->firstWhere('bed_id', $bed->id)['breakfast']);
        $this->assertSame(1, $rows->firstWhere('bed_id', $bed->id)['people']);
        $this->assertSame(0, $rows->firstWhere('bed_id', $otherBed->id)['breakfast']);
        $this->assertSame('free', $rows->firstWhere('bed_id', $otherBed->id)['status']);
    }

}
