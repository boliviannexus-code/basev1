<?php

namespace Tests\Feature\Occupancy;

use App\Models\AvailabilityDay;
use App\Models\BathroomType;
use App\Models\BedType;
use App\Models\Company;
use App\Models\OccupancyBlock;
use App\Models\PrivateSpaceType;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use App\Models\User;
use Database\Seeders\AccommodationCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WeeklyOccupancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_data_shows_only_approved_active_resources(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $visiblePrivate = $this->privateSpace($company, ['title' => 'Casa visible']);
        $this->privateSpace($company, ['title' => 'Casa borrador', 'status' => 'draft', 'approved_at' => null]);
        $activeWithoutApprovalDate = $this->privateSpace($company, ['title' => 'Casa activa sin fecha de aprobacion', 'approved_at' => null]);
        [$shared, $roomA] = $this->sharedSpaceWithRooms($company);
        $roomB = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $shared->id,
            'bathroom_type_id' => BathroomType::where('slug', 'privado')->firstOrFail()->id,
            'name' => '103',
            'title' => '103',
            'status' => 'inactive',
        ]);

        $response = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-03']))
            ->assertOk()
            ->json();

        $labels = collect($response['rows'])->pluck('label');
        $this->assertSame('2026-06-03', $response['week_start']);
        $this->assertSame('2026-06-09', $response['week_end']);
        $this->assertSame('2026-06-02', $response['previous_week']);
        $this->assertSame('2026-06-04', $response['next_week']);
        $this->assertCount(7, $response['dates']);
        $this->assertTrue($labels->contains('Casa visible - Cap. 4'));
        $this->assertTrue($labels->contains('Casa activa sin fecha de aprobacion - Cap. 4'));
        $this->assertTrue($labels->contains('Hotel Central'));
        $this->assertTrue($labels->contains($roomA->name));
        $this->assertFalse($labels->contains('Casa borrador'));
        $this->assertFalse($labels->contains($roomB->name));
        $this->assertSame($visiblePrivate->id, collect($response['rows'])->firstWhere('label', 'Casa visible - Cap. 4')['space_id']);
        $this->assertSame($activeWithoutApprovalDate->id, collect($response['rows'])->firstWhere('label', 'Casa activa sin fecha de aprobacion - Cap. 4')['space_id']);
    }

    public function test_week_data_defaults_to_seven_days_starting_yesterday(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $this->privateSpace($company);

            $response = $this
                ->actingAs($user)
                ->getJson(route('occupancy.week-data'))
                ->assertOk()
                ->json();

            $this->assertSame('2026-06-09', $response['week_start']);
            $this->assertSame('2026-06-15', $response['week_end']);
            $this->assertSame('2026-06-09', $response['current_week']);
            $this->assertSame([
                '2026-06-09',
                '2026-06-10',
                '2026-06-11',
                '2026-06-12',
                '2026-06-13',
                '2026-06-14',
                '2026-06-15',
            ], collect($response['dates'])->pluck('date')->all());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shared_room_label_includes_bed_description(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [, $roomA] = $this->sharedSpaceWithRooms($company);
        $bedType = BedType::where('slug', 'cama-matrimonial')->firstOrFail();
        $roomA->beds()->create([
            'company_id' => $company->id,
            'bed_type_id' => $bedType->id,
            'quantity' => 2,
            'capacity_per_bed' => $bedType->capacity,
            'total_capacity' => 2 * $bedType->capacity,
        ]);

        $labels = collect($this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-03']))
            ->assertOk()
            ->json('rows'))->pluck('label');

        $this->assertTrue($labels->contains('101 - 2 Cama matrimonial'));
    }

    public function test_private_block_paints_inclusive_dates_in_week_grid(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);
        $this->openAvailability($company, $space, null, '2026-06-01', '2026-06-06');

        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'type' => 'manual_block',
            'title' => 'Bloqueado - Casa Vista',
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-01']))
            ->assertOk()
            ->json('rows');

        $row = collect($rows)->firstWhere('space_id', $space->id);
        $statuses = collect($row['cells'])->pluck('status', 'date');

        $this->assertSame('manual_block', $statuses['2026-06-01']);
        $this->assertSame('manual_block', $statuses['2026-06-05']);
        $this->assertSame('available', $statuses['2026-06-06']);
    }

    public function test_closed_availability_day_blocks_occupancy_grid_operations(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        AvailabilityDay::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-03',
            'price' => '120',
            'status' => 'closed',
        ]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-01']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-03');

        $this->assertSame('unavailable', $cell['status']);
        $this->assertTrue($cell['closed_by_availability']);
        $this->assertSame([], $cell['actions']);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.blocks.store'), [
                'space_id' => $space->id,
                'type' => 'manual_block',
                'title' => 'Bloqueo',
                'start_date' => '2026-06-03',
                'end_date' => '2026-06-03',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_can_create_update_and_delete_private_block(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);
        $this->openAvailability($company, $space, null, '2026-06-01', '2026-06-05');

        $blockId = $this
            ->actingAs($user)
            ->postJson(route('occupancy.blocks.store'), [
                'space_id' => $space->id,
                'type' => 'maintenance',
                'title' => 'Mantenimiento',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-03',
            ])
            ->assertCreated()
            ->json('data.id');

        $this
            ->actingAs($user)
            ->patchJson(route('occupancy.blocks.update', $blockId), [
                'space_id' => $space->id,
                'type' => 'owner_use',
                'title' => 'Uso propietario',
                'start_date' => '2026-06-04',
                'end_date' => '2026-06-05',
            ])
            ->assertOk();

        $this->assertDatabaseHas('occupancy_blocks', [
            'id' => $blockId,
            'type' => 'owner_use',
            'title' => 'Uso propietario',
            'start_date' => '2026-06-04',
            'end_date' => '2026-06-05',
        ]);

        $this
            ->actingAs($user)
            ->deleteJson(route('occupancy.blocks.destroy', $blockId))
            ->assertOk();

        $this->assertSoftDeleted('occupancy_blocks', ['id' => $blockId]);
    }

    public function test_shared_room_overlap_is_validated_per_room(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space, $roomA, $roomB] = $this->sharedSpaceWithRooms($company);
        $this->openAvailability($company, $space, $roomA, '2026-06-01', '2026-06-05');
        $this->openAvailability($company, $space, $roomB, '2026-06-01', '2026-06-05');

        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $roomA->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-05',
        ]);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.blocks.store'), [
                'space_id' => $space->id,
                'space_room_id' => $roomB->id,
                'type' => 'manual_block',
                'title' => 'Habitacion 102',
                'start_date' => '2026-06-02',
                'end_date' => '2026-06-04',
            ])
            ->assertCreated();

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.blocks.store'), [
                'space_id' => $space->id,
                'space_room_id' => $roomA->id,
                'type' => 'manual_block',
                'title' => 'Habitacion 101',
                'start_date' => '2026-06-02',
                'end_date' => '2026-06-04',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_date');
    }

    public function test_company_user_cannot_manage_other_company_space(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user] = $this->companyUser();
        $otherSpace = $this->privateSpace(Company::factory()->create());

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.blocks.store'), [
                'space_id' => $otherSpace->id,
                'type' => 'manual_block',
                'title' => 'Externo',
                'start_date' => '2026-06-01',
                'end_date' => '2026-06-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('space_id');
    }

    private function companyUser(): array
    {
        Permission::findOrCreate('occupancy.view');
        Permission::findOrCreate('occupancy.manage');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['occupancy.view', 'occupancy.manage']);

        return [$user, $company];
    }

    private function openAvailability(Company $company, Space $space, ?SpaceRoom $room, string $startDate, string $endDate): void
    {
        foreach (Carbon::parse($startDate)->daysUntil(Carbon::parse($endDate)->addDay()) as $date) {
            AvailabilityDay::factory()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'space_room_id' => $room?->id,
                'date' => $date->toDateString(),
                'price' => '120',
                'status' => 'available',
            ]);
        }
    }

    private function privateSpace(Company $company, array $attributes = []): Space
    {
        return Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::where('slug', 'privado')->firstOrFail()->id,
            'private_space_type_id' => PrivateSpaceType::where('slug', 'casa')->firstOrFail()->id,
            'shared_space_type_id' => null,
            'title' => 'Casa Vista',
            'name' => 'Casa Vista',
            'max_capacity' => 4,
            'status' => 'active',
            'approved_at' => now(),
            ...$attributes,
        ]);
    }

    private function sharedSpaceWithRooms(Company $company): array
    {
        $space = Space::factory()->create([
            'company_id' => $company->id,
            'space_mode_id' => SpaceMode::where('slug', 'compartido')->firstOrFail()->id,
            'private_space_type_id' => null,
            'shared_space_type_id' => SharedSpaceType::where('slug', 'hotel')->firstOrFail()->id,
            'title' => null,
            'name' => 'Hotel Central',
            'status' => 'active',
            'approved_at' => now(),
        ]);

        $bathroomType = BathroomType::where('slug', 'privado')->firstOrFail();
        $roomA = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'bathroom_type_id' => $bathroomType->id,
            'name' => '101',
            'title' => '101',
            'status' => 'active',
        ]);
        $roomB = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'bathroom_type_id' => $bathroomType->id,
            'name' => '102',
            'title' => '102',
            'status' => 'active',
        ]);

        return [$space, $roomA, $roomB];
    }
}
