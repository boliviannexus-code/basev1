<?php

namespace Tests\Feature\Availability;

use App\Models\AvailabilityStatus;
use App\Models\BathroomType;
use App\Models\Company;
use App\Models\OccupancyBlock;
use App\Models\PrivateSpaceType;
use App\Models\RoomBed;
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

class AvailabilityGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_shows_active_company_resources_for_week(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $private = $this->privateSpace($company, ['title' => 'Casa visible']);
            $this->privateSpace($company, ['title' => 'Casa borrador', 'status' => 'draft']);
            [$shared, $room] = $this->sharedSpaceWithRooms($company);
            $this->privateSpace(Company::factory()->create(), ['title' => 'Casa externa']);

            $response = $this
                ->actingAs($user)
                ->getJson(route('availability.week-data'))
                ->assertOk()
                ->json();

            $labels = collect($response['rows'])->pluck('label');

            $this->assertSame('2026-06-10', $response['week_start']);
            $this->assertSame('2026-06-16', $response['week_end']);
            $this->assertFalse($response['can_go_previous']);
            $this->assertCount(7, $response['dates']);
            $this->assertTrue($labels->contains('Casa visible - Cap. 4'));
            $this->assertTrue($labels->contains('Hotel Central'));
            $this->assertTrue($labels->contains('Habitacion A - Hab. 101 - 2 Cama individual'));
            $this->assertFalse($labels->contains('Casa borrador - Cap. 4'));
            $this->assertFalse($labels->contains('Casa externa - Cap. 4'));
            $this->assertSame($private->id, collect($response['rows'])->firstWhere('label', 'Casa visible - Cap. 4')['space_id']);
            $this->assertSame($shared->id, collect($response['rows'])->firstWhere('label', 'Hotel Central')['space_id']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_private_space_status_can_be_saved(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'status' => 'closed',
                'notes' => 'Mantenimiento',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('availability_statuses', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-12',
            'status' => 'closed',
            'source' => 'manual',
            'notes' => 'Mantenimiento',
        ]);
    }

    public function test_missing_status_is_presented_as_available(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('availability.week-data', ['week_start' => '2026-06-12']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-12');

        $this->assertSame('available', $cell['status']);
        $this->assertNull($cell['source']);
        $this->assertNull($cell['availability_status_id']);
    }

    public function test_grid_clamps_past_requested_date_to_today(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user] = $this->companyUser();

            $response = $this
                ->actingAs($user)
                ->getJson(route('availability.week-data', ['week_start' => '2026-06-01']))
                ->assertOk()
                ->json();

            $this->assertSame('2026-06-10', $response['week_start']);
            $this->assertSame('2026-06-16', $response['week_end']);
            $this->assertFalse($response['can_go_previous']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_returning_manual_status_to_available_soft_deletes_record(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);
        $status = AvailabilityStatus::query()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-12',
            'status' => 'reserved',
            'source' => 'manual',
        ]);

        $this
            ->actingAs($user)
            ->patchJson(route('availability.status.update', $status), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'status' => 'available',
            ])
            ->assertOk();

        $this->assertSoftDeleted('availability_statuses', [
            'id' => $status->id,
        ]);
    }

    public function test_past_date_status_cannot_be_saved(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);

            $this
                ->actingAs($user)
                ->postJson(route('availability.status.store'), [
                    'space_id' => $space->id,
                    'date' => '2026-06-09',
                    'status' => 'closed',
                ])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_shared_space_requires_room_for_day_save(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space] = $this->sharedSpaceWithRooms($company);

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'status' => 'available',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('space_room_id');
    }

    public function test_company_user_cannot_manage_other_company_resource(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user] = $this->companyUser();
        $otherSpace = $this->privateSpace(Company::factory()->create());

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $otherSpace->id,
                'date' => '2026-06-12',
                'status' => 'closed',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('space_id');
    }

    public function test_shared_rooms_can_have_different_statuses_on_same_date(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space, $roomA, $roomB] = $this->sharedSpaceWithRooms($company);

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $space->id,
                'space_room_id' => $roomA->id,
                'date' => '2026-06-12',
                'status' => 'closed',
            ])
            ->assertOk();

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $space->id,
                'space_room_id' => $roomB->id,
                'date' => '2026-06-12',
                'status' => 'occupied',
            ])
            ->assertOk();

        $rows = $this
            ->actingAs($user)
            ->getJson(route('availability.week-data', ['week_start' => '2026-06-12']))
            ->assertOk()
            ->json('rows');
        $roomARow = collect($rows)->firstWhere('room_id', $roomA->id);
        $roomBRow = collect($rows)->firstWhere('room_id', $roomB->id);

        $this->assertSame('closed', collect($roomARow['cells'])->firstWhere('date', '2026-06-12')['status']);
        $this->assertSame('occupied', collect($roomBRow['cells'])->firstWhere('date', '2026-06-12')['status']);
    }

    public function test_occupancy_block_is_reflected_and_locked_in_availability_grid(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);
        $block = OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'type' => 'maintenance',
            'title' => 'Mantenimiento',
            'start_date' => '2026-06-12',
            'end_date' => '2026-06-12',
        ]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('availability.week-data', ['week_start' => '2026-06-12']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-12');

        $this->assertSame('closed', $cell['status']);
        $this->assertSame('ocupabilidad', $cell['source']);
        $this->assertSame($block->id, $cell['occupancy_block_id']);
        $this->assertSame([], $cell['actions']);

        $this
            ->actingAs($user)
            ->postJson(route('availability.status.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'status' => 'available',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    private function companyUser(): array
    {
        Permission::findOrCreate('availability.view');
        Permission::findOrCreate('availability.manage');
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['availability.view', 'availability.manage']);

        return [$user, $company];
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
            'name' => 'Habitacion A',
            'room_number' => '101',
            'title' => 'Habitacion A',
            'status' => 'active',
        ]);
        $roomB = SpaceRoom::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'bathroom_type_id' => $bathroomType->id,
            'name' => 'Habitacion B',
            'room_number' => '102',
            'title' => 'Habitacion B',
            'status' => 'active',
        ]);
        RoomBed::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $roomA->id,
            'quantity' => 2,
            'capacity_per_bed' => 1,
            'total_capacity' => 2,
        ]);

        return [$space, $roomA, $roomB];
    }
}
