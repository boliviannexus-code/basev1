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
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('account_statement_items', [
            'stay_id' => $stay->id,
            'type' => 'discount',
            'description' => 'Cortesia administracion',
            'unit_price' => '-25.00',
            'total' => '-25.00',
            'status' => 'active',
        ]);
        $this->assertSame('25.00', $stay->accountStatement->refresh()->discount_total);
        $this->assertSame('75.00', $stay->accountStatement->refresh()->balance);
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

    private function context(bool $superAdmin): array
    {
        Permission::findOrCreate('occupancy.manage');

        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
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
