<?php

namespace Tests\Feature\Availability;

use App\Models\AvailabilityDay;
use App\Models\BathroomType;
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

class AvailabilityGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_grid_shows_active_company_resources_for_thirty_days(): void
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
                ->getJson(route('availability.grid-data'))
                ->assertOk()
                ->json();

            $labels = collect($response['rows'])->pluck('label');

            $this->assertSame('2026-06-10', $response['start_date']);
            $this->assertSame('2026-07-09', $response['end_date']);
            $this->assertCount(30, $response['dates']);
            $this->assertTrue($labels->contains('Casa visible - Cap. 4'));
            $this->assertTrue($labels->contains('Hotel Central'));
            $this->assertTrue($labels->contains($room->name));
            $this->assertFalse($labels->contains('Casa borrador - Cap. 4'));
            $this->assertFalse($labels->contains('Casa externa - Cap. 4'));
            $this->assertSame($private->id, collect($response['rows'])->firstWhere('label', 'Casa visible - Cap. 4')['space_id']);
            $this->assertSame($shared->id, collect($response['rows'])->firstWhere('label', 'Hotel Central')['space_id']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_private_space_day_can_be_saved(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        $this
            ->actingAs($user)
            ->patchJson(route('availability.day.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'price' => '150.50',
                'status' => 'closed',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('availability_days', [
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-12',
            'price' => '150.50',
            'status' => 'closed',
        ]);
    }

    public function test_day_without_price_is_presented_as_closed_and_cannot_be_enabled(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('availability.grid-data', ['start_date' => '2026-06-12']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-12');

        $this->assertSame('closed', $cell['status']);
        $this->assertSame('closed', $cell['stored_status']);
        $this->assertFalse($cell['has_price']);

        $this
            ->actingAs($user)
            ->patchJson(route('availability.day.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'price' => null,
                'status' => 'available',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('price');
    }

    public function test_shared_space_requires_room_for_day_save(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space] = $this->sharedSpaceWithRooms($company);

        $this
            ->actingAs($user)
            ->patchJson(route('availability.day.store'), [
                'space_id' => $space->id,
                'date' => '2026-06-12',
                'price' => '90',
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
            ->patchJson(route('availability.day.store'), [
                'space_id' => $otherSpace->id,
                'date' => '2026-06-12',
                'price' => '80',
                'status' => 'available',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('space_id');
    }

    public function test_bulk_update_applies_price_and_status_to_matching_resources(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);
        $this->sharedSpaceWithRooms($company);

        $response = $this
            ->actingAs($user)
            ->postJson(route('availability.bulk'), [
                'type' => 'private',
                'space_id' => $space->id,
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-14',
                'apply_price' => true,
                'price' => '120',
                'apply_status' => true,
                'status' => 'closed',
            ])
            ->assertOk()
            ->json();

        $this->assertSame(3, $response['data']['count']);
        $this->assertSame(3, AvailabilityDay::query()
            ->where('company_id', $company->id)
            ->where('space_id', $space->id)
            ->where('price', '120')
            ->where('status', 'closed')
            ->count());
    }

    public function test_occupancy_block_marks_available_day_as_sold_out(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        AvailabilityDay::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-12',
            'price' => '150',
            'status' => 'available',
        ]);

        OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'start_date' => '2026-06-12',
            'end_date' => '2026-06-12',
        ]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('availability.grid-data', ['start_date' => '2026-06-12']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-12');

        $this->assertSame('sold_out', $cell['status']);
        $this->assertSame('available', $cell['stored_status']);
        $this->assertTrue($cell['is_sold_out']);
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
