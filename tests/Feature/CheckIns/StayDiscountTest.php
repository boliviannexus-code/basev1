<?php

namespace Tests\Feature\CheckIns;

use App\Models\CheckInGroup;
use App\Models\Company;
use App\Models\Guest;
use App\Models\Space;
use App\Models\Stay;
use App\Models\User;
use App\Services\CheckIn\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StayDiscountTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_apply_fixed_discount_to_stay_account(): void
    {
        [$user, $stay] = $this->context(superAdmin: true);

        $this
            ->actingAs($user)
            ->post(route('stays.discounts.store', $stay), [
                'amount' => 25,
                'description' => 'Cortesia administracion',
                'transaction_pin' => '1234',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'discount',
            'description' => 'Cortesia administracion',
            'unit_price' => '-25.00',
            'total' => '-25.00',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $this->assertSame('25.00', $stay->accountStatement->refresh()->discount_total);
        $this->assertSame('75.00', $stay->accountStatement->refresh()->balance);
    }

    public function test_discount_requires_transaction_pin(): void
    {
        [$user, $stay] = $this->context(superAdmin: true);

        $this
            ->actingAs($user)
            ->post(route('stays.discounts.store', $stay), [
                'amount' => 25,
                'description' => 'Cortesia administracion',
            ])
            ->assertSessionHasErrors(['transaction_pin'], null, 'stayDiscount');

        $this->assertDatabaseMissing('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'discount',
        ]);
    }

    public function test_discount_is_assigned_to_pin_owner(): void
    {
        [$sessionUser, $stay] = $this->context(superAdmin: true);
        $cashOwner = User::factory()->create([
            'company_id' => $sessionUser->company_id,
            'transaction_pin' => Hash::make('9876'),
        ]);

        $this
            ->actingAs($sessionUser)
            ->post(route('stays.discounts.store', $stay), [
                'amount' => 25,
                'description' => 'Cortesia autorizada por caja',
                'transaction_pin' => '9876',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'discount',
            'created_by' => $cashOwner->id,
        ]);
    }

    public function test_non_super_admin_cannot_apply_fixed_discount(): void
    {
        [$user, $stay] = $this->context(superAdmin: false);

        $this
            ->actingAs($user)
            ->post(route('stays.discounts.store', $stay), [
                'amount' => 25,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'discount',
        ]);
    }

    public function test_unattended_case_links_to_existing_discount_form_and_can_checkout_after_discount(): void
    {
        [$user, $stay] = $this->context(superAdmin: true);
        Permission::findOrCreate('unattended-checkouts.manage');
        $user->givePermissionTo('unattended-checkouts.manage');
        $stay->update(['check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay()]);
        $stay->checkInGroup->update(['check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay()]);

        $this->actingAs($user)->get(route('unattended-checkouts.index'))
            ->assertOk()->assertSee(route('stays.account', $stay).'#stay-discount', false);
        $this->get(route('stays.account', $stay))->assertOk()
            ->assertSee('id="stay-discount"', false)
            ->assertSee('Volver a casos sin atención');
        $this->from(route('stays.account', $stay))->post(route('stays.discounts.store', $stay), [
            'amount' => 100, 'description' => 'Descuento para resolver salida pendiente',
            'transaction_pin' => '1234',
        ])->assertRedirect(route('stays.account', $stay))->assertSessionHasNoErrors();
        $this->assertSame('0.00', $stay->accountStatement->refresh()->balance);
        $this->get(route('unattended-checkouts.index'))->assertOk()->assertDontSee('Aplicar descuento');
        $this->post(route('unattended-checkouts.resolve', $stay), [
            'action' => 'checkout', 'expected_check_out_date' => $stay->check_out_date->toDateString(),
            'reason' => 'Saldo ajustado y salida registrada desde casos sin atencion.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('checked_out', $stay->refresh()->status);
    }

    public function test_unattended_discount_link_is_hidden_without_existing_discount_authorization(): void
    {
        [$user, $stay] = $this->context(superAdmin: false);
        Permission::findOrCreate('unattended-checkouts.manage');
        $user->givePermissionTo('unattended-checkouts.manage');
        $stay->update(['check_in_date' => today()->subDays(2), 'check_out_date' => today()->subDay()]);
        $this->actingAs($user)->get(route('unattended-checkouts.index'))
            ->assertOk()->assertDontSee('Aplicar descuento');
    }

    private function context(bool $superAdmin): array
    {
        Permission::findOrCreate('occupancy.manage');

        $company = Company::factory()->create();
        $user = User::factory()->create([
            'company_id' => $company->id,
            'transaction_pin' => Hash::make('1234'),
        ]);
        $user->givePermissionTo('occupancy.manage');

        if ($superAdmin) {
            Role::findOrCreate('super_admin');
            $user->assignRole('super_admin');
        }

        $guest = Guest::factory()->create(['company_id' => $company->id]);
        $space = Space::factory()->create(['company_id' => $company->id]);
        $group = CheckInGroup::factory()->create([
            'company_id' => $company->id,
            'main_guest_id' => $guest->id,
            'total_people' => 1,
        ]);
        $stay = Stay::factory()->create([
            'company_id' => $company->id,
            'check_in_group_id' => $group->id,
            'holder_guest_id' => $guest->id,
            'space_id' => $space->id,
            'price_per_night_bob' => 100,
            'currency' => 'BOB',
        ]);

        app(AccountStatementService::class)->createForStay($stay);

        return [$user, $stay];
    }
}
