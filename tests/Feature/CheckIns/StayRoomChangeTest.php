<?php

namespace Tests\Feature\CheckIns;

use App\Models\AvailabilityStatus;
use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Space;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use App\Services\Availability\AvailabilityStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class StayRoomChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_day_room_change_moves_entire_stay_to_new_space(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $stay, $targetSpace] = $this->context(
            checkIn: now()->toDateString(),
            checkOut: now()->addDays(2)->toDateString(),
        );
        $sourceSpaceId = $stay->space_id;

        $this
            ->actingAs($user)
            ->post(route('stays.room-change.store', $stay), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'price_per_night_bob' => 80,
            ])
            ->assertRedirect(route('occupancy.index'));

        $stay->refresh();

        $this->assertSame($targetSpace->id, $stay->space_id);
        $this->assertSame('occupied', $stay->status);
        $this->assertSame('160.00', $stay->accountStatement->refresh()->balance);
        $this->assertSoftDeleted('availability_statuses', [
            'space_id' => $sourceSpaceId,
            'date' => now()->toDateString(),
            'status' => 'occupied',
        ]);
        $this->assertDatabaseHas('availability_statuses', [
            'space_id' => $targetSpace->id,
            'date' => now()->toDateString(),
            'status' => 'occupied',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->toDateString(),
            'unit_price' => '80.00',
            'total' => '80.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->addDay()->toDateString(),
            'unit_price' => '80.00',
            'total' => '80.00',
            'status' => 'active',
        ]);
    }

    public function test_late_room_change_splits_remaining_nights_and_transfers_net_balance(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $stay, $targetSpace] = $this->context(
            checkIn: now()->subDay()->toDateString(),
            checkOut: now()->addDays(2)->toDateString(),
        );

        $this
            ->actingAs($user)
            ->post(route('stays.room-change.store', $stay), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'price_per_night_bob' => 70,
            ])
            ->assertRedirect(route('occupancy.index'));

        $original = $stay->refresh();
        $newStay = Stay::query()
            ->where('check_in_group_id', $stay->check_in_group_id)
            ->whereKeyNot($stay->id)
            ->firstOrFail();

        $this->assertSame('checked_out', $original->status);
        $this->assertSame(now()->toDateString(), $original->check_out_date->toDateString());
        $this->assertSame('0.00', $original->accountStatement->refresh()->balance);
        $this->assertSame($targetSpace->id, $newStay->space_id);
        $this->assertSame(now()->toDateString(), $newStay->check_in_date->toDateString());
        $this->assertSame(now()->addDays(2)->toDateString(), $newStay->check_out_date->toDateString());
        $this->assertSame(2, $newStay->nights);
        $this->assertSame('240.00', $newStay->accountStatement->refresh()->balance);
        $this->assertSame(1, $newStay->guests()->count());

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $original->id,
            'type' => 'adjustment',
            'total' => '-100.00',
            'source' => 'system',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $newStay->id,
            'type' => 'adjustment',
            'total' => '100.00',
            'source' => 'system',
        ]);
        $this->assertSoftDeleted('availability_statuses', [
            'space_id' => $original->space_id,
            'date' => now()->toDateString(),
            'status' => 'occupied',
        ]);
        $this->assertDatabaseHas('availability_statuses', [
            'space_id' => $targetSpace->id,
            'date' => now()->toDateString(),
            'status' => 'occupied',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $newStay->id,
            'type' => 'lodging_night',
            'date' => now()->toDateString(),
            'unit_price' => '70.00',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $newStay->id,
            'type' => 'lodging_night',
            'date' => now()->addDay()->toDateString(),
            'unit_price' => '70.00',
        ]);
    }

    public function test_stay_update_price_changes_only_future_nights(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $stay] = $this->context(
            checkIn: now()->subDays(2)->toDateString(),
            checkOut: now()->addDays(2)->toDateString(),
            price: 50,
        );

        $this
            ->actingAs($user)
            ->patch(route('stays.update', $stay), [
                'check_out_date' => now()->addDays(2)->toDateString(),
                'people_count' => 1,
                'price_per_night_bob' => 80,
                'currency' => 'BOB',
                'exchange_rate' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->subDays(2)->toDateString(),
            'unit_price' => '50.00',
            'total' => '50.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->subDay()->toDateString(),
            'unit_price' => '50.00',
            'total' => '50.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->toDateString(),
            'unit_price' => '80.00',
            'total' => '80.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'lodging_night',
            'date' => now()->addDay()->toDateString(),
            'unit_price' => '80.00',
            'total' => '80.00',
            'status' => 'active',
        ]);
    }

    public function test_room_change_rejects_unavailable_target_space(): void
    {
        $this->travelTo(now()->startOfDay());
        [$user, $stay, $targetSpace] = $this->context(
            checkIn: now()->toDateString(),
            checkOut: now()->addDays(2)->toDateString(),
        );
        AvailabilityStatus::query()->create([
            'company_id' => $targetSpace->company_id,
            'space_id' => $targetSpace->id,
            'date' => now()->toDateString(),
            'status' => 'closed',
            'source' => 'manual',
        ]);

        $this
            ->actingAs($user)
            ->post(route('stays.room-change.store', $stay), [
                'resource_type' => 'private_space',
                'space_id' => $targetSpace->id,
                'price_per_night_bob' => 70,
            ])
            ->assertSessionHasErrors();
    }

    private function context(string $checkIn, string $checkOut, float $price = 100): array
    {
        Permission::findOrCreate('occupancy.manage');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        $guest = Guest::factory()->create(['company_id' => $company->id]);
        $sourceSpace = Space::factory()->create(['company_id' => $company->id, 'status' => 'active', 'max_capacity' => 2]);
        $targetSpace = Space::factory()->create(['company_id' => $company->id, 'status' => 'active', 'max_capacity' => 2]);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'main_guest_id' => $guest->id,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'status' => 'checked_in',
        ]);
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $guest->id,
            'space_id' => $sourceSpace->id,
            'people_count' => 1,
            'check_in_date' => $checkIn,
            'check_out_date' => $checkOut,
            'nights' => CarbonImmutable::parse($checkIn)->diffInDays(CarbonImmutable::parse($checkOut)),
            'price_per_night_bob' => $price,
            'currency' => 'BOB',
            'status' => 'occupied',
        ]);
        $stay->guests()->attach($guest->id, [
            'company_id' => $company->id,
            'is_holder' => true,
        ]);
        app(AvailabilityStatusService::class)->markRangeOccupied($company->id, $stay, $user);
        app(AccountStatementService::class)->createForStay($stay);

        return [$user, $stay, $targetSpace];
    }
}
