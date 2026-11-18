<?php

namespace Tests\Feature\CheckIns;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Space;
use App\Models\Stay;
use App\Models\User;
use App\Services\Availability\AvailabilityGridService;
use App\Services\Availability\AvailabilityStatusService;
use App\Services\CheckIn\AccountStatementService;
use App\Services\CheckIn\CheckOutService;
use App\Services\Occupancy\OccupancyGridActionService;
use App\Services\Occupancy\OccupancyGridService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_repair_releases_old_checkout_markers_and_preserves_new_occupancy(): void
    {
        [$user, $stay] = $this->context();
        $stay->update(['check_out_date' => today()->addDays(3)->toDateString()]);
        $dates = [today()->toDateString(), today()->addDay()->toDateString()];
        app(AvailabilityStatusService::class)->markOccupiedRange($stay, $dates, $user);
        $stay->update(['status' => 'checked_out', 'checked_out_at' => now()]);

        $newStay = $this->createStayForGroup($stay, 100);
        $newStay->update([
            'space_id' => $stay->space_id,
            'check_in_date' => today()->addDay()->toDateString(),
        ]);

        $migration = require database_path('migrations/2026_11_16_180000_release_availability_after_completed_checkouts.php');
        $migration->up();

        $this->assertDatabaseMissing('availability_statuses', [
            'space_id' => $stay->space_id,
            'date' => $dates[0],
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('availability_statuses', [
            'space_id' => $stay->space_id,
            'date' => $dates[1],
            'status' => 'occupied',
            'deleted_at' => null,
        ]);
    }

    public function test_early_check_out_releases_unused_paid_dates_without_changing_charges(): void
    {
        $this->travelTo(now()->startOfWeek()->addDay()->setHour(10));
        [$user, $stay] = $this->context();
        $stay->space->update(['status' => 'active', 'approved_at' => now()]);
        $stay->update([
            'check_in_date' => today()->subDay()->toDateString(),
            'check_out_date' => today()->addDay()->toDateString(),
            'nights' => 2,
        ]);
        $this->payStay($stay);
        $statement = $stay->accountStatement->fresh();
        app(AvailabilityStatusService::class)->markOccupiedRange($stay, [
            today()->subDay()->toDateString(),
            today()->toDateString(),
        ], $user);

        $this->actingAs($user)
            ->postJson(route('occupancy.check-out.store', $stay))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(today()->addDay()->toDateString(), $stay->refresh()->check_out_date->toDateString());
        $this->assertSame(today()->toDateString(), $stay->checked_out_at->toDateString());
        $this->assertSame(2, $stay->nights);
        $this->assertSame($statement->getAttributes(), $statement->fresh()->getAttributes());

        $grid = app(OccupancyGridService::class)->weekData($user->company_id, [
            'week_start' => today()->subDay()->toDateString(),
        ]);
        $row = collect($grid['rows'])->firstWhere('space_id', $stay->space_id);
        $this->assertSame('checked_out', collect($row['cells'])->firstWhere('date', today()->subDay()->toDateString())['status']);
        $this->assertSame('available', collect($row['cells'])->firstWhere('date', today()->toDateString())['status']);
        $this->assertSame('checkout-dirty', $row['cells'][0]['tone']);
        $dirtyCell = collect($row['cells'])->firstWhere('date', today()->toDateString());
        $this->assertTrue($dirtyCell['cleaning_pending']);
        $this->assertNull($dirtyCell['reference_stay_id']);
        $this->assertNull($dirtyCell['holder_guest_name']);
        $this->assertNull($dirtyCell['check_in_code']);
        $context = app(OccupancyGridActionService::class)->cellContext($user->company_id, [
            'space_id' => $stay->space_id, 'date' => today()->toDateString(),
        ]);
        $this->assertContains('mark_clean', array_column($context['actions'], 'key'));
        $this->assertNotContains('check_in', array_column($context['actions'], 'key'));
        $previousContext = app(OccupancyGridActionService::class)->cellContext($user->company_id, [
            'space_id' => $stay->space_id, 'date' => today()->subDay()->toDateString(),
        ]);
        $this->assertSame($stay->id, $previousContext['stay']->id);
        $this->assertContains('mark_clean', array_column($previousContext['actions'], 'key'));
        $this->getJson(route('check-ins.available-resources', [
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDay()->toDateString(),
        ]))->assertOk()->assertJsonFragment(['status' => 'dirty', 'disabled' => true]);
        try {
            app(AvailabilityStatusService::class)->validateRangeAvailable($user->company_id, [
                'space_id' => $stay->space_id,
                'check_in_date' => today()->toDateString(),
                'check_out_date' => today()->addDay()->toDateString(),
            ]);
            $this->fail('A dirty room must reject check-in.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('sucia', $exception->getMessage());
        }
        $this->postJson(route('occupancy.cleaning.store', $stay))->assertOk();
        $this->assertSame($user->id, (int) $stay->refresh()->cleaned_by);
        $this->assertNotNull($stay->cleaned_at);
        $grid = app(OccupancyGridService::class)->weekData($user->company_id, ['week_start' => today()->subDay()->toDateString()]);
        $row = collect($grid['rows'])->firstWhere('space_id', $stay->space_id);

        $cleanCell = collect($row['cells'])->firstWhere('date', today()->toDateString());
        $this->assertSame('available', $cleanCell['tone']);
        $this->assertSame('checkout-clean', $row['cells'][0]['tone']);
        $this->assertNull($cleanCell['reference_stay_id']);
        $this->assertSame($dirtyCell['holder_guest_name'], $cleanCell['holder_guest_name']);
        foreach (collect($row['cells'])->where('date', '>', today()->toDateString()) as $futureCell) {
            $this->assertSame('available', $futureCell['tone']);
            $this->assertNull($futureCell['reference_stay_id']);
            $this->assertNull($futureCell['holder_guest_name']);
            $this->assertNull($futureCell['check_in_code']);
        }
        $unused = Space::factory()->create(['company_id' => $user->company_id, 'status' => 'active']);
        $unusedGrid = app(OccupancyGridService::class)->weekData($user->company_id, ['week_start' => today()->toDateString()]);
        $unusedRow = collect($unusedGrid['rows'])->firstWhere('space_id', $unused->id);
        $this->assertSame('available', $unusedRow['cells'][0]['tone']);
        $this->assertNull($unusedRow['cells'][0]['reference_stay_id']);

        foreach ([today(), today()->addDay()] as $date) {
            $this->assertSame('available', collect($row['cells'])->firstWhere('date', $date->toDateString())['status']);
            $availability = app(AvailabilityGridService::class)->gridData($user->company_id, ['week_start' => today()->toDateString()]);
            $availabilityRow = collect($availability['rows'])->firstWhere('space_id', $stay->space_id);
            if ($date->isToday()) {
                $this->assertSame('available', collect($availabilityRow['cells'])->firstWhere('date', $date->toDateString())['status']);
                app(AvailabilityStatusService::class)->validateRangeAvailable($user->company_id, [
                    'space_id' => $stay->space_id,
                    'check_in_date' => today()->toDateString(),
                    'check_out_date' => today()->addDay()->toDateString(),
                ]);
            }
            $context = app(OccupancyGridActionService::class)->cellContext($user->company_id, [
                'space_id' => $stay->space_id,
                'date' => $date->toDateString(),
            ]);
            if ($date->isToday()) {
                $this->assertNull($context['stay']);
                $this->assertContains('check_in', array_column($context['actions'], 'key'));
            } else {
                $this->assertNull($context['stay']);
                $this->assertNotContains('check_in', array_column($context['actions'], 'key'));
            }
        }
        $unusedContext = app(OccupancyGridActionService::class)->cellContext($user->company_id, [
            'space_id' => $unused->id, 'date' => today()->toDateString(),
        ]);
        $this->assertContains('check_in', array_column($unusedContext['actions'], 'key'));
    }

    public function test_check_out_is_blocked_when_selected_stay_has_pending_debt(): void
    {
        [$user, $stay] = $this->context();
        $secondStay = $this->createStayForGroup($stay, price: 50);
        $this->payStay($secondStay);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.check-out.store', $stay))
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertSame('occupied', $stay->refresh()->status);
        $this->assertSame('occupied', $secondStay->refresh()->status);
        $this->assertSame('checked_in', $stay->checkInGroup->refresh()->status);
    }

    public function test_paid_stay_can_check_out_even_when_another_group_stay_has_debt(): void
    {
        [$user, $stay] = $this->context();
        $secondStay = $this->createStayForGroup($stay, price: 50);
        $this->payStay($stay);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.check-out.store', $stay))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('checked_out', $stay->refresh()->status);
        $this->assertSame('occupied', $secondStay->refresh()->status);
        $this->assertSame('checked_in', $stay->checkInGroup->refresh()->status);
    }

    public function test_pending_debt_check_out_modal_links_to_collect_payment(): void
    {
        [$user, $stay] = $this->context();

        $summary = app(CheckOutService::class)->debtSummary($stay);

        $this
            ->actingAs($user)
            ->view('occupancy.partials.check-out-modal', [
                'stay' => $stay,
                'group' => $stay->checkInGroup,
                'summary' => $summary,
            ])
            ->assertSee('Hay deuda pendiente')
            ->assertSee('Cobrar')
            ->assertSee(route('stays.payments.create', ['stay' => $stay, 'scope' => 'stay']), false)
            ->assertDontSee(route('stays.payments.create', ['stay' => $stay, 'scope' => 'group']), false)
            ->assertDontSee('Ver estado de cuenta');
    }

    public function test_check_out_keeps_group_open_when_other_stays_are_occupied(): void
    {
        [$user, $stay] = $this->context();
        $secondStay = $this->createStayForGroup($stay, price: 50);
        $this->payStay($stay);
        $this->payStay($secondStay);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.check-out.store', $stay))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('checked_out', $stay->refresh()->status);
        $this->assertSame('occupied', $secondStay->refresh()->status);
        $this->assertSame('checked_in', $stay->checkInGroup->refresh()->status);
    }

    public function test_check_out_closes_group_when_last_occupied_stay_is_checked_out(): void
    {
        [$user, $stay] = $this->context();
        $this->payStay($stay);

        $this
            ->actingAs($user)
            ->postJson(route('occupancy.check-out.store', $stay))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('checked_out', $stay->refresh()->status);
        $this->assertSame('checked_out', $stay->checkInGroup->refresh()->status);
    }

    public function test_cleaning_requires_checkout_and_permission_and_company_ownership(): void
    {
        [$user, $stay] = $this->context();
        $this->actingAs($user)->postJson(route('occupancy.cleaning.store', $stay))->assertUnprocessable();
        $this->payStay($stay);
        $this->postJson(route('occupancy.check-out.store', $stay))->assertOk();

        $otherUser = User::factory()->create(['company_id' => $user->company_id]);
        $this->actingAs($otherUser)->postJson(route('occupancy.cleaning.store', $stay))->assertForbidden();
        $foreignUser = User::factory()->create(['company_id' => Company::factory()->create()->id]);
        $foreignUser->givePermissionTo('occupancy.manage');
        $this->actingAs($foreignUser)->postJson(route('occupancy.cleaning.store', $stay))->assertNotFound();
        $this->actingAs($user)->postJson(route('occupancy.cleaning.store', $stay))->assertOk();
        $cleanedAt = $stay->refresh()->cleaned_at->toDateTimeString();
        $this->travel(1)->minutes();
        $this->postJson(route('occupancy.cleaning.store', $stay))->assertOk();
        $this->assertSame($cleanedAt, $stay->refresh()->cleaned_at->toDateTimeString());
    }

    public function test_dirty_room_remains_pending_on_following_days_until_cleaned(): void
    {
        [$user, $stay] = $this->context();
        $stay->space->update(['status' => 'active']);
        $this->payStay($stay);
        $this->actingAs($user)->postJson(route('occupancy.check-out.store', $stay))->assertOk();
        $this->travel(2)->days();
        $grid = app(OccupancyGridService::class)->weekData($user->company_id, ['week_start' => today()->toDateString()]);
        $row = collect($grid['rows'])->firstWhere('space_id', $stay->space_id);
        $this->assertSame('available', $row['cells'][0]['status']);
        $this->assertTrue($row['cells'][0]['cleaning_pending']);
        $this->postJson(route('occupancy.cleaning.store', $stay))->assertOk();
        $grid = app(OccupancyGridService::class)->weekData($user->company_id, ['week_start' => today()->toDateString()]);
        $row = collect($grid['rows'])->firstWhere('space_id', $stay->space_id);
        $this->assertSame('available', $row['cells'][0]['status']);
    }

    private function createStayForGroup(Stay $baseStay, int $price): Stay
    {
        $space = Space::factory()->create(['company_id' => $baseStay->company_id]);
        $stay = Stay::factory()->create([
            'company_id' => $baseStay->company_id,
            'check_in_group_id' => $baseStay->check_in_group_id,
            'holder_guest_id' => $baseStay->holder_guest_id,
            'space_id' => $space->id,
            'people_count' => 1,
            'check_in_date' => $baseStay->check_in_date->toDateString(),
            'check_out_date' => $baseStay->check_out_date->toDateString(),
            'nights' => 1,
            'price_per_night_bob' => $price,
            'currency' => 'BOB',
            'status' => 'occupied',
        ]);
        app(AccountStatementService::class)->createForStay($stay);

        return $stay;
    }

    private function payStay(Stay $stay): void
    {
        $statementService = app(AccountStatementService::class);
        $statement = $statementService->recalculate($stay->accountStatement);
        $statementService->recordPayment($stay, (float) $statement->balance, 'Pago total');
    }

    private function context(): array
    {
        Permission::findOrCreate('occupancy.manage');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo('occupancy.manage');
        $guest = Guest::factory()->create(['company_id' => $company->id]);
        $space = Space::factory()->create(['company_id' => $company->id]);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'main_guest_id' => $guest->id,
            'total_people' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'status' => 'checked_in',
        ]);
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $guest->id,
            'space_id' => $space->id,
            'people_count' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'nights' => 1,
            'price_per_night_bob' => 100,
            'currency' => 'BOB',
            'status' => 'occupied',
        ]);
        app(AccountStatementService::class)->createForStay($stay);

        return [$user, $stay];
    }
}
