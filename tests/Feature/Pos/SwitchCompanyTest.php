<?php

namespace Tests\Feature\Pos;

use App\Models\Company;
use App\Models\SpaceCashRegister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SwitchCompanyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_select_a_company_and_return_to_global(): void
    {
        $user = User::factory()->create(['company_id' => null, 'is_active' => true]);
        $user->assignRole(Role::findOrCreate('super_admin'));
        $company = Company::factory()->create(['is_active' => true]);
        $this->actingAs($user)->post(route('company.switch'), ['company_id' => $company->id])->assertRedirect(route('dashboard'));
        $this->assertSame($company->id, $user->refresh()->company_id);
        $this->actingAs($user)->post(route('company.switch'), ['company_id' => ''])->assertRedirect(route('dashboard'));
        $this->assertNull($user->refresh()->company_id);
    }

    public function test_non_super_admin_is_forbidden_and_open_cash_cannot_be_abandoned(): void
    {
        $company = Company::factory()->create(['is_active' => true]);
        $other = Company::factory()->create(['is_active' => true]);
        $user = User::factory()->create(['company_id' => $company->id, 'is_active' => true]);
        $this->actingAs($user)->post(route('company.switch'), ['company_id' => $other->id])->assertForbidden();
        $user->assignRole(Role::findOrCreate('super_admin'));
        SpaceCashRegister::factory()->create(['company_id' => $company->id, 'user_id' => $user->id, 'status' => 'open']);
        $this->actingAs($user)->post(route('company.switch'), ['company_id' => $other->id])->assertSessionHasErrors('company_id');
        $this->assertSame($company->id, $user->refresh()->company_id);
    }
}
