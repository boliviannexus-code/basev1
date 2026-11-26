<?php

namespace Tests\Feature\Reservations;

use App\Models\Company;
use App\Models\OccupancyBlock;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Space;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ReservationMoveTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_move_reservation_to_available_space_and_grid_exposes_move_action(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $reservation, $targetSpace] = $this->context();

        $this
            ->actingAs($user)
            ->postJson(route('admin.reservations.move.store', $reservation), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'check_in' => now()->addDays(5)->toDateString(),
                'check_out' => now()->addDays(7)->toDateString(),
                'price_per_night' => 80,
                'notes' => 'Cambio solicitado por el huesped',
            ])
            ->assertOk()
            ->assertJsonPath('refresh_occupancy', true);

        $reservation->refresh();
        $group = $reservation->reservationGroup()->firstOrFail();
        $block = $reservation->occupancyBlock()->withTrashed()->firstOrFail();

        $this->assertSame($targetSpace->id, $reservation->space_id);
        $this->assertNull($reservation->space_room_id);
        $this->assertSame(now()->addDays(5)->toDateString(), $reservation->check_in->toDateString());
        $this->assertSame(now()->addDays(7)->toDateString(), $reservation->check_out->toDateString());
        $this->assertSame(2, $reservation->nights);
        $this->assertSame('80.00', $reservation->price_per_person);
        $this->assertSame('160.00', $reservation->total_amount);
        $this->assertSame($targetSpace->id, $block->space_id);
        $this->assertSame(now()->addDays(5)->toDateString(), $block->start_date->toDateString());
        $this->assertSame(now()->addDays(6)->toDateString(), $block->end_date->toDateString());
        $this->assertSame('160.00', $group->refresh()->total_amount);
        $this->assertSame('160.00', $group->accountStatement->refresh()->balance);

        $this->assertDatabaseHas('account_statement_items', [
            'reservation_id' => $reservation->id,
            'date' => now()->addDays(5)->toDateString(),
            'unit_price' => '80.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'reservation_id' => $reservation->id,
            'date' => now()->addDays(6)->toDateString(),
            'unit_price' => '80.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'reservation_id' => $reservation->id,
            'date' => now()->addDays(2)->toDateString(),
            'status' => 'cancelled',
        ]);

        $grid = $this
            ->actingAs($user)
            ->getJson(route('occupancy.week-data', ['week_start' => now()->addDays(5)->toDateString()]))
            ->assertOk()
            ->json();

        $cells = collect($grid['rows'])
            ->flatMap(fn (array $row): array => $row['cells'] ?? []);
        $movedCell = $cells->first(fn (array $cell): bool => (int) ($cell['reservation_id'] ?? 0) === (int) $reservation->id);

        $this->assertNotNull($movedCell);
        $this->assertSame('reserved', $movedCell['status']);
        $this->assertContains('move_reservation', $movedCell['actions']);
    }

    public function test_reservation_move_rejects_past_check_in(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $reservation, $targetSpace] = $this->context();

        $this
            ->actingAs($user)
            ->postJson(route('admin.reservations.move.store', $reservation), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'check_in' => now()->subDay()->toDateString(),
                'check_out' => now()->addDay()->toDateString(),
                'price_per_night' => 80,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('check_in');
    }

    public function test_reservation_move_rejects_unavailable_target_space(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $reservation, $targetSpace] = $this->context();

        OccupancyBlock::factory()->create([
            'company_id' => $targetSpace->company_id,
            'space_id' => $targetSpace->id,
            'space_room_id' => null,
            'room_bed_unit_id' => null,
            'status' => 'active',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $this
            ->actingAs($user)
            ->postJson(route('admin.reservations.move.store', $reservation), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'check_in' => now()->addDays(5)->toDateString(),
                'check_out' => now()->addDays(7)->toDateString(),
                'price_per_night' => 80,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('space_id');

        $this->assertNotSame($targetSpace->id, $reservation->refresh()->space_id);
    }

    public function test_flexible_room_can_change_between_bed_and_full_room_and_rejects_conflicts(): void
    {
        [$user, $reservation] = $this->context();
        $mode = \App\Models\SpaceMode::query()->firstOrCreate(['slug' => 'compartido'], ['name' => 'Compartido', 'is_active' => true]);
        $space = Space::factory()->create(['company_id' => $user->company_id, 'space_mode_id' => $mode->id, 'status' => 'active']);
        $room = \App\Models\SpaceRoom::factory()->create(['company_id' => $user->company_id, 'space_id' => $space->id, 'status' => 'active', 'sale_mode' => 'flexible', 'max_capacity' => 2]);
        $bed = \App\Models\RoomBedUnit::factory()->create(['company_id' => $user->company_id, 'space_room_id' => $room->id, 'status' => 'active']);
        $otherBed = \App\Models\RoomBedUnit::factory()->create(['company_id' => $user->company_id, 'space_room_id' => $room->id, 'status' => 'active']);
        $payload = ['resource_type' => 'shared_bed_unit', 'space_id' => $space->id, 'space_room_id' => $room->id, 'room_bed_unit_id' => $bed->id, 'check_in' => $reservation->check_in->toDateString(), 'check_out' => $reservation->check_out->toDateString(), 'price_per_night' => 80];
        $this->actingAs($user)->postJson(route('admin.reservations.move.store', $reservation), $payload)->assertOk();
        $this->assertDatabaseHas('reservation_bed_units', ['reservation_id' => $reservation->id, 'room_bed_unit_id' => $bed->id]);
        $this->get(route('admin.reservations.move.create', $reservation))->assertOk()->assertSee('data-move-full-room-switch', false)->assertSee('data-full-room-available="1"', false);
        $payload['resource_type'] = 'shared_room';
        $payload['room_bed_unit_id'] = null;
        $conflict = OccupancyBlock::factory()->create(['company_id' => $user->company_id, 'space_id' => $space->id, 'space_room_id' => $room->id, 'room_bed_unit_id' => $otherBed->id, 'status' => 'active', 'start_date' => $payload['check_in'], 'end_date' => $reservation->check_out->copy()->subDay()]);
        $this->postJson(route('admin.reservations.move.store', $reservation), $payload)->assertUnprocessable()->assertJsonValidationErrors('space_id');
        $this->get(route('admin.reservations.move.create', $reservation))->assertOk()->assertSee('data-full-room-available="0"', false);
        $conflict->delete();
        $this->postJson(route('admin.reservations.move.store', $reservation), $payload)->assertOk();
        $this->assertDatabaseHas('reservation_rooms', ['reservation_id' => $reservation->id, 'space_room_id' => $room->id]);
        $this->assertDatabaseMissing('reservation_bed_units', ['reservation_id' => $reservation->id]);
        $payload['resource_type'] = 'shared_bed_unit';
        $payload['room_bed_unit_id'] = $bed->id;
        $this->postJson(route('admin.reservations.move.store', $reservation), $payload)->assertOk();
        $this->assertDatabaseHas('reservation_bed_units', ['reservation_id' => $reservation->id, 'room_bed_unit_id' => $bed->id]);
        $group = $reservation->reservationGroup()->firstOrFail();
        $this->get(route('admin.reservation-groups.edit', $group))->assertOk()->assertSee('data-reservation-full-room', false);
        $edit = ['guest_name' => $group->guest_name, 'reservations' => [$reservation->id => [
            'check_in' => $payload['check_in'], 'check_out' => $payload['check_out'], 'nights' => 2, 'guests' => 1,
            'price_per_night_bob' => 80, 'price_per_night_usd' => 11.49, 'exchange_rate' => 6.96,
            'full_room' => 1, 'bed_unit_id' => $bed->id,
        ]]];
        $conflict->restore();
        $this->patchJson(route('admin.reservation-groups.update', $group), $edit)->assertUnprocessable();
        $this->assertDatabaseHas('reservation_bed_units', ['reservation_id' => $reservation->id, 'room_bed_unit_id' => $bed->id]);
        $conflict->delete();
        $this->patchJson(route('admin.reservation-groups.update', $group), $edit)->assertOk()->assertJsonPath('redirect_url', route('occupancy.index'));
        $this->assertDatabaseHas('reservation_rooms', ['reservation_id' => $reservation->id, 'space_room_id' => $room->id]);
        $edit['reservations'][$reservation->id]['full_room'] = 0;
        $this->patchJson(route('admin.reservation-groups.update', $group), $edit)->assertOk();
        $this->assertDatabaseHas('reservation_bed_units', ['reservation_id' => $reservation->id, 'room_bed_unit_id' => $bed->id]);
    }

    private function context(): array
    {
        Permission::findOrCreate('reservations.manage');
        Permission::findOrCreate('occupancy.view');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['reservations.manage', 'occupancy.view']);
        $sourceSpace = Space::factory()->create(['company_id' => $company->id, 'status' => 'active', 'max_capacity' => 2]);
        $targetSpace = Space::factory()->create(['company_id' => $company->id, 'status' => 'active', 'max_capacity' => 2]);
        $checkIn = CarbonImmutable::parse(now()->addDays(2)->toDateString());
        $checkOut = $checkIn->addDays(2);
        $total = 200.0;
        $group = ReservationGroup::factory()->create([
            'company_id' => $company->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'subtotal_amount' => $total,
            'total_amount' => $total,
            'balance_amount' => $total,
            'status' => 'pending_payment',
            'payment_status' => 'pending',
        ]);
        $block = OccupancyBlock::factory()->create([
            'company_id' => $company->id,
            'space_id' => $sourceSpace->id,
            'space_room_id' => null,
            'room_bed_unit_id' => null,
            'status' => 'active',
            'start_date' => $checkIn->toDateString(),
            'end_date' => $checkOut->subDay()->toDateString(),
        ]);
        $reservation = Reservation::factory()->create([
            'company_id' => $company->id,
            'reservation_group_id' => $group->id,
            'user_id' => null,
            'space_id' => $sourceSpace->id,
            'space_room_id' => null,
            'occupancy_block_id' => $block->id,
            'check_in' => $checkIn->toDateString(),
            'check_out' => $checkOut->toDateString(),
            'nights' => 2,
            'guests' => 1,
            'price_per_person' => 100,
            'subtotal_amount' => $total,
            'total_amount' => $total,
            'advance_amount' => 0,
            'balance_amount' => $total,
            'currency' => 'BOB',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'payment_method' => 'internal',
            'hold_expires_at' => null,
        ]);
        app(AccountStatementService::class)->createForReservationGroup($group->refresh()->load('reservations'));

        return [$user, $reservation, $targetSpace];
    }
}
