<?php

namespace Tests\Feature\CheckIns;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\PaymentMethod;
use App\Models\Space;
use App\Models\SpaceCashRegister;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LodgingPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_collect_partial_stay_payment_with_open_cash_register(): void
    {
        [$user, $stay, $cashRegister, $method] = $this->context();

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'stay',
                'payment_method_id' => $method->id,
                'amount' => 40,
                'reference' => 'QR-001',
            ])
            ->assertRedirect(route('occupancy.index'));

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'payment',
            'total' => '-40.00',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('space_cash_lodging_payments', [
            'space_cash_register_id' => $cashRegister->id,
            'stay_id' => $stay->id,
            'payment_method_id' => $method->id,
            'amount_original' => '40.00',
            'amount_bob' => '40.00',
            'reference' => 'QR-001',
        ]);
        $this->assertSame('60.00', $stay->accountStatement->refresh()->balance);
    }

    public function test_lodging_payment_requires_open_cash_register(): void
    {
        [$user, $stay,, $method] = $this->context(openRegister: false);

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'stay',
                'payment_method_id' => $method->id,
                'amount' => 40,
            ])
            ->assertSessionHasErrors(['amount'], null, 'stayPayment');
    }

    public function test_group_payment_distributes_amount_across_stays(): void
    {
        [$user, $stay, $cashRegister, $method] = $this->context();
        $secondStay = Stay::factory()->create([
            'company_id' => $stay->company_id,
            'check_in_group_id' => $stay->check_in_group_id,
            'holder_guest_id' => $stay->holder_guest_id,
            'space_id' => $stay->space_id,
            'people_count' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => now()->addDay()->toDateString(),
            'nights' => 1,
            'price_per_night_bob' => 50,
            'currency' => 'BOB',
        ]);
        app(AccountStatementService::class)->createForStay($secondStay);

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'group',
                'payment_method_id' => $method->id,
                'amount' => 130,
            ])
            ->assertRedirect(route('occupancy.index'));

        $this->assertDatabaseCount('space_cash_lodging_payments', 2);
        $this->assertDatabaseHas('space_cash_lodging_payments', [
            'space_cash_register_id' => $cashRegister->id,
            'stay_id' => $stay->id,
            'amount_original' => '100.00',
        ]);
        $this->assertDatabaseHas('space_cash_lodging_payments', [
            'space_cash_register_id' => $cashRegister->id,
            'stay_id' => $secondStay->id,
            'amount_original' => '30.00',
        ]);
    }

    public function test_user_can_collect_full_balance_and_check_out_when_departure_is_today(): void
    {
        [$user, $stay,, $method] = $this->context(checkOutDate: now()->toDateString());

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'group',
                'action' => 'collect_checkout',
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertRedirect(route('occupancy.index'))
            ->assertSessionHas('success');

        $this->assertSame('0.00', $stay->accountStatement->refresh()->balance);
        $this->assertSame('checked_out', $stay->refresh()->status);
        $this->assertSame('checked_out', $stay->checkInGroup->refresh()->status);
    }

    public function test_collect_and_check_out_requires_departure_today(): void
    {
        [$user, $stay,, $method] = $this->context(checkOutDate: now()->addDay()->toDateString());

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'group',
                'action' => 'collect_checkout',
                'payment_method_id' => $method->id,
                'amount' => 100,
            ])
            ->assertSessionHasErrors(['action'], null, 'stayPayment');

        $this->assertSame('100.00', $stay->accountStatement->refresh()->balance);
        $this->assertSame('occupied', $stay->refresh()->status);
        $this->assertSame('checked_in', $stay->checkInGroup->refresh()->status);
    }

    public function test_collect_and_check_out_requires_full_balance_payment(): void
    {
        [$user, $stay,, $method] = $this->context(checkOutDate: now()->toDateString());

        $this
            ->actingAs($user)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'group',
                'action' => 'collect_checkout',
                'payment_method_id' => $method->id,
                'amount' => 90,
            ])
            ->assertSessionHasErrors(['amount'], null, 'stayPayment');

        $this->assertSame('100.00', $stay->accountStatement->refresh()->balance);
        $this->assertSame('occupied', $stay->refresh()->status);
    }

    public function test_two_users_can_collect_same_stay_into_their_own_cash_registers(): void
    {
        [$firstUser, $stay, $firstCashRegister, $method] = $this->context();
        $secondUser = User::factory()->create(['company_id' => $firstUser->company_id]);
        $secondUser->givePermissionTo(['occupancy.manage', 'space-cash.access']);
        $secondCashRegister = SpaceCashRegister::factory()->create([
            'company_id' => $secondUser->company_id,
            'user_id' => $secondUser->id,
            'opening_amount' => 15,
            'status' => 'open',
        ]);

        $this
            ->actingAs($firstUser)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'stay',
                'payment_method_id' => $method->id,
                'amount' => 40,
            ])
            ->assertRedirect(route('occupancy.index'));

        $this
            ->actingAs($secondUser)
            ->post(route('stays.payments.store', $stay), [
                'scope' => 'stay',
                'payment_method_id' => $method->id,
                'amount' => 30,
            ])
            ->assertRedirect(route('occupancy.index'));

        $this->assertDatabaseHas('space_cash_lodging_payments', [
            'space_cash_register_id' => $firstCashRegister->id,
            'user_id' => $firstUser->id,
            'stay_id' => $stay->id,
            'amount_original' => '40.00',
            'receipt_number' => 'ESP-'.$firstUser->id.'-000001',
        ]);
        $this->assertDatabaseHas('space_cash_lodging_payments', [
            'space_cash_register_id' => $secondCashRegister->id,
            'user_id' => $secondUser->id,
            'stay_id' => $stay->id,
            'amount_original' => '30.00',
            'receipt_number' => 'ESP-'.$secondUser->id.'-000001',
        ]);
        $this->assertSame('30.00', $stay->accountStatement->refresh()->balance);
    }

    private function context(bool $openRegister = true, ?string $checkOutDate = null): array
    {
        Permission::findOrCreate('occupancy.manage');
        Permission::findOrCreate('space-cash.access');
        $checkOutDate ??= now()->addDay()->toDateString();

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        $user->givePermissionTo(['occupancy.manage', 'space-cash.access']);
        $cashRegister = $openRegister
            ? SpaceCashRegister::factory()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'opening_amount' => 10,
                'status' => 'open',
            ])
            : null;
        $method = PaymentMethod::query()->create([
            'company_id' => $company->id,
            'name' => 'QR',
            'is_active' => true,
        ]);
        $guest = Guest::factory()->create(['company_id' => $company->id]);
        $space = Space::factory()->create(['company_id' => $company->id]);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'main_guest_id' => $guest->id,
            'total_people' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => $checkOutDate,
        ]);
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $guest->id,
            'space_id' => $space->id,
            'people_count' => 1,
            'check_in_date' => now()->toDateString(),
            'check_out_date' => $checkOutDate,
            'nights' => 1,
            'price_per_night_bob' => 100,
            'currency' => 'BOB',
        ]);
        app(AccountStatementService::class)->createForStay($stay);

        return [$user, $stay, $cashRegister, $method];
    }
}
