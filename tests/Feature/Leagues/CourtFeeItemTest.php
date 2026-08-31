<?php

namespace Tests\Feature\Leagues;

use App\Models\Company;
use App\Models\CourtFeeItem;
use App\Models\CourtFee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CourtFeeItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_can_manage_items_and_total_is_displayed(): void
    {
        [$company, $user] = $this->companyUser([
            'court-fee-items.view', 'court-fee-items.create', 'court-fee-items.update', 'court-fee-items.delete',
        ]);

        $fee = CourtFee::query()->create(['company_id' => $company->id, 'name' => 'Primera A', 'is_active' => true]);
        $this->actingAs($user)->post(route('court-fees.items.store', $fee), ['name' => 'Arbitraje', 'cost' => '125.50'])
            ->assertRedirect(route('court-fees.show', $fee));
        $this->actingAs($user)->post(route('court-fees.items.store', $fee), ['name' => 'Marcación', 'cost' => '24.25'])
            ->assertRedirect(route('court-fees.show', $fee));

        $this->assertDatabaseHas('court_fee_items', ['court_fee_id' => $fee->id, 'name' => 'Arbitraje', 'cost' => '125.50']);
        $this->actingAs($user)->get(route('court-fees.show', $fee))
            ->assertOk()->assertSee('Bs 149,75')->assertSee('Arbitraje');
    }

    public function test_items_are_isolated_by_company_and_cost_accepts_at_most_two_decimals(): void
    {
        [$company, $user] = $this->companyUser(['court-fee-items.view', 'court-fee-items.create']);
        $other = Company::factory()->create();
        $fee = CourtFee::query()->create(['company_id' => $company->id, 'name' => 'Propio']);
        $otherFee = CourtFee::query()->create(['company_id' => $other->id, 'name' => 'Ajeno']);
        CourtFeeItem::query()->create(['court_fee_id' => $otherFee->id, 'name' => 'Ítem ajeno', 'cost' => 99]);

        $this->actingAs($user)->get(route('court-fees.index'))->assertOk()->assertDontSee('Ajeno');
        $this->actingAs($user)->post(route('court-fees.items.store', $fee), ['name' => 'Inválido', 'cost' => '10.123'])
            ->assertSessionHasErrors('cost');
        $this->assertDatabaseMissing('court_fee_items', ['court_fee_id' => $fee->id, 'name' => 'Inválido']);
    }

    private function companyUser(array $permissions): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }
        $user->givePermissionTo($permissions);

        return [$company, $user];
    }
}
