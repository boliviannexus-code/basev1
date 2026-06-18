<?php

namespace Tests\Feature\CheckIns;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Space;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use App\Services\CheckIn\CheckOutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CheckOutTest extends TestCase
{
    use RefreshDatabase;

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
