<?php

namespace Tests\Feature\Occupancy;

use App\Models\AvailabilityDay;
use App\Models\AvailabilityStatus;
use App\Models\BathroomType;
use App\Models\BedType;
use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\OccupancyBlock;
use App\Models\PrivateSpaceType;
use App\Models\Reservation;
use App\Models\ReservationBedUnit;
use App\Models\RoomBedUnit;
use App\Models\SharedSpaceType;
use App\Models\Space;
use App\Models\SpaceMode;
use App\Models\SpaceRoom;
use App\Models\Stay;
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
        [$space, $roomA] = $this->sharedSpaceWithRooms($company);
        $roomA->update(['sale_mode' => 'full_room']);
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
            ->getJson(route('occupancy.week-data', [
                'week_start' => '2026-06-03',
                'view' => 'shared:'.$space->id,
            ]))
            ->assertOk()
            ->json('rows'))->pluck('label');

        $this->assertTrue($labels->contains('101 - 2 Cama matrimonial'));
    }

    public function test_flexible_and_individual_room_labels_only_show_the_room_name(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space, $flexibleRoom, $individualRoom] = $this->sharedSpaceWithRooms($company);
        $bedType = BedType::where('slug', 'cama-individual')->firstOrFail();

        foreach ([$flexibleRoom, $individualRoom] as $room) {
            $room->beds()->create([
                'company_id' => $company->id,
                'bed_type_id' => $bedType->id,
                'quantity' => 2,
                'capacity_per_bed' => $bedType->capacity,
                'total_capacity' => 2 * $bedType->capacity,
            ]);
            RoomBedUnit::factory()->create([
                'company_id' => $company->id,
                'space_room_id' => $room->id,
                'bed_type_id' => $bedType->id,
                'label' => 'Cama 1',
                'sort_order' => 1,
            ]);
        }

        $flexibleRoom->update(['sale_mode' => 'flexible']);
        $individualRoom->update(['sale_mode' => 'bed_unit']);

        $rows = collect($this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', [
                'week_start' => '2026-06-03',
                'view' => 'shared:'.$space->id,
            ]))
            ->assertOk()
            ->json('rows'));

        $flexibleRow = $rows->first(fn (array $row): bool => ($row['type'] ?? null) === 'shared_room'
            && (int) ($row['room_id'] ?? 0) === $flexibleRoom->id);
        $individualRow = $rows->first(fn (array $row): bool => ($row['type'] ?? null) === 'shared_room'
            && (int) ($row['room_id'] ?? 0) === $individualRoom->id);
        $bedRows = $rows->where('type', 'shared_bed_unit');

        $this->assertSame($flexibleRoom->name, $flexibleRow['label']);
        $this->assertSame($individualRoom->name, $individualRow['label']);
        $this->assertTrue($bedRows->contains(fn (array $row): bool => str_contains($row['label'], 'Cama individual')));
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

    public function test_closed_availability_status_blocks_occupancy_grid_operations(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        $space = $this->privateSpace($company);

        AvailabilityStatus::query()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => null,
            'date' => '2026-06-03',
            'status' => 'closed',
            'source' => 'manual',
        ]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-01']))
            ->assertOk()
            ->json('rows');

        $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-03');

        $this->assertSame('closed', $cell['status']);
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

    public function test_occupied_availability_status_allows_occupancy_grid_operations(): void
    {
        Carbon::setTestNow('2026-06-03 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);

            AvailabilityStatus::query()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'space_room_id' => null,
                'date' => '2026-06-03',
                'status' => 'occupied',
                'source' => 'manual',
            ]);

            $rows = $this
                ->actingAs($user)
                ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-01']))
                ->assertOk()
                ->json('rows');

            $cell = collect(collect($rows)->firstWhere('space_id', $space->id)['cells'])->firstWhere('date', '2026-06-03');

            $this->assertSame('occupied', $cell['status']);
            $this->assertFalse($cell['closed_by_availability']);
            $this->assertFalse($cell['blocked_by_availability']);
            $this->assertSame(['create_block', 'maintenance', 'owner_use', 'unavailable'], $cell['actions']);

            $actions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-03',
                ]))
                ->assertOk()
                ->json('actions');

            $this->assertSame(['check_in', 'reservation', 'block'], collect($actions)->pluck('key')->all());

            $this
                ->actingAs($user)
                ->postJson(route('occupancy.blocks.store'), [
                    'space_id' => $space->id,
                    'type' => 'manual_block',
                    'title' => 'Bloqueo',
                    'start_date' => '2026-06-03',
                    'end_date' => '2026-06-03',
                ])
                ->assertCreated();
        } finally {
            Carbon::setTestNow();
        }
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

    public function test_cell_actions_follow_date_rules(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);

            $pastActions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-09',
                ]))
                ->assertOk()
                ->json('actions');

            $todayActions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]))
                ->assertOk()
                ->json('actions');

            $futureActions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-11',
                ]))
                ->assertOk()
                ->json('actions');

            OccupancyBlock::factory()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'type' => 'occupied',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ]);

            $occupiedTodayActions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]))
                ->assertOk()
                ->json('actions');

            $this->assertSame([], $pastActions);
            $this->assertSame(['check_in', 'reservation', 'block'], collect($todayActions)->pluck('key')->all());
            $this->assertSame(['reservation', 'block'], collect($futureActions)->pluck('key')->all());
            $this->assertSame(['check_in', 'reservation', 'block'], collect($occupiedTodayActions)->pluck('key')->all());
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_pending_checkout_today_allows_reservation_and_block_but_not_check_in_or_check_out(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);
            $guest = Guest::factory()->create(['company_id' => $company->id]);
            $group = CheckInGroup::factory()->create([
                'company_id' => $company->id,
                'main_guest_id' => $guest->id,
                'check_in_date' => '2026-06-09',
                'check_out_date' => '2026-06-10',
                'status' => 'checked_in',
            ]);
            Stay::factory()->create([
                'company_id' => $company->id,
                'check_in_group_id' => $group->id,
                'holder_guest_id' => $guest->id,
                'space_id' => $space->id,
                'check_in_date' => '2026-06-09',
                'check_out_date' => '2026-06-10',
                'status' => 'occupied',
            ]);

            $actions = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]))
                ->assertOk()
                ->json('actions');

            $actionKeys = collect($actions)->pluck('key')->all();

            $this->assertSame(['reservation', 'block'], $actionKeys);
            $this->assertNotContains('check_in', $actionKeys);
            $this->assertNotContains('check_out', $actionKeys);

            $this
                ->actingAs($user)
                ->get(route('occupancy.check-in.modal', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');

            $this
                ->actingAs($user)
                ->postJson(route('occupancy.blocks.store'), [
                    'space_id' => $space->id,
                    'type' => 'manual_block',
                    'title' => 'Bloqueo post salida',
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-10',
                ])
                ->assertCreated();
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reservation_over_pending_checkout_shows_reservation_actions(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);
            $guest = Guest::factory()->create(['company_id' => $company->id]);
            $group = CheckInGroup::factory()->create([
                'company_id' => $company->id,
                'main_guest_id' => $guest->id,
                'check_in_date' => '2026-06-09',
                'check_out_date' => '2026-06-10',
                'status' => 'checked_in',
            ]);
            Stay::factory()->create([
                'company_id' => $company->id,
                'check_in_group_id' => $group->id,
                'holder_guest_id' => $guest->id,
                'space_id' => $space->id,
                'check_in_date' => '2026-06-09',
                'check_out_date' => '2026-06-10',
                'status' => 'occupied',
            ]);
            $block = OccupancyBlock::factory()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'type' => 'unavailable',
                'title' => 'Reserva siguiente',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ]);
            $reservation = Reservation::factory()->confirmed()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'occupancy_block_id' => $block->id,
                'guest_name' => 'Reserva Hoy',
                'check_in' => '2026-06-10',
                'check_out' => '2026-06-11',
            ]);

            $response = $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]))
                ->assertOk();

            $actionKeys = collect($response->json('actions'))->pluck('key')->all();

            $this->assertSame(['move_reservation', 'extra_charge', 'view_reservation'], $actionKeys);
            $this->assertNotContains('reservation', $actionKeys);
            $this->assertNotContains('block', $actionKeys);
            $this->assertSame('reserved', $response->json('occupancy_state.status'));
            $this->assertSame($reservation->id, $response->json('occupancy_state.reservation_id'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_action_modals_validate_date_rules_on_backend(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user, $company] = $this->companyUser();
            $space = $this->privateSpace($company);

            $this
                ->actingAs($user)
                ->get(route('occupancy.check-in.modal', [
                    'space_id' => $space->id,
                    'date' => '2026-06-11',
                ]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');

            $this
                ->actingAs($user)
                ->get(route('occupancy.check-out.modal', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');

            OccupancyBlock::factory()->create([
                'company_id' => $company->id,
                'space_id' => $space->id,
                'type' => 'occupied',
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-10',
            ]);

            $this
                ->actingAs($user)
                ->get(route('occupancy.check-out.modal', [
                    'space_id' => $space->id,
                    'date' => '2026-06-10',
                ]), ['X-Requested-With' => 'XMLHttpRequest'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_cell_action_modal_rejects_other_company_space(): void
    {
        Carbon::setTestNow('2026-06-10 10:00:00');

        try {
            $this->seed(AccommodationCatalogSeeder::class);
            [$user] = $this->companyUser();
            $otherSpace = $this->privateSpace(Company::factory()->create());

            $this
                ->actingAs($user)
                ->getJson(route('occupancy.cell-actions', [
                    'space_id' => $otherSpace->id,
                    'date' => '2026-06-10',
                ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('space_id');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_bed_unit_reservation_is_visible_with_guest_name_in_occupancy_grid(): void
    {
        $this->seed(AccommodationCatalogSeeder::class);
        [$user, $company] = $this->companyUser();
        [$space, $room] = $this->sharedSpaceWithRooms($company);
        $room->update(['sale_mode' => 'bed_unit']);
        $bedType = BedType::where('slug', 'cama-individual')->firstOrFail();
        $unitA = RoomBedUnit::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'bed_type_id' => $bedType->id,
            'label' => 'Cama A',
            'sort_order' => 1,
        ]);
        RoomBedUnit::factory()->create([
            'company_id' => $company->id,
            'space_room_id' => $room->id,
            'bed_type_id' => $bedType->id,
            'label' => 'Cama B',
            'sort_order' => 2,
        ]);
        $reservation = Reservation::factory()->confirmed()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $room->id,
            'guest_name' => 'Ana Camacho',
            'check_in' => '2026-06-03',
            'check_out' => '2026-06-05',
        ]);
        $block = OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $space->id,
            'space_room_id' => $room->id,
            'room_bed_unit_id' => $unitA->id,
            'type' => 'unavailable',
            'title' => 'Reserva '.$reservation->code.' confirmada',
            'start_date' => '2026-06-03',
            'end_date' => '2026-06-04',
        ]);
        ReservationBedUnit::factory()->create([
            'reservation_id' => $reservation->id,
            'room_bed_unit_id' => $unitA->id,
            'occupancy_block_id' => $block->id,
            'guest_name' => 'Ana Camacho',
        ]);
        $reservation->update(['occupancy_block_id' => $block->id]);

        $rows = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => '2026-06-03']))
            ->assertOk()
            ->json('rows');

        $roomRow = collect($rows)->firstWhere('room_id', $room->id);
        $bedRow = collect($rows)->firstWhere('room_bed_unit_id', $unitA->id);
        $bedCell = collect($bedRow['cells'])->firstWhere('date', '2026-06-03');
        $roomCell = collect($roomRow['cells'])->firstWhere('date', '2026-06-03');

        $this->assertSame('shared_bed_unit', $bedRow['type']);
        $this->assertSame('Ana Camacho', $bedCell['guest_name']);
        $this->assertSame('Ana Camacho', $bedCell['label']);
        $this->assertSame('reserved', $roomCell['status']);
        $this->assertSame('Parcial', $roomCell['label']);
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
