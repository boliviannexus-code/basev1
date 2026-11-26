<?php

namespace Tests\Feature\SpaceCash;

use App\Models\Company;
use App\Models\SpaceCashRegister;
use App\Models\User;
use App\Services\CashRegisterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminCashCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_closes_another_users_unified_register_with_counted_cash(): void
    {
        [$admin, $owner] = $this->users();
        $pos = app(CashRegisterService::class)->openForUser($owner, 100);
        $space = SpaceCashRegister::query()->where('cash_register_id', $pos->id)->firstOrFail();
        $this->actingAs($admin)->get(route('space-cash.history'))->assertOk()->assertSee($owner->name);
        $this->get(route('space-cash.show', $space))->assertOk()->assertSee('Cerrar caja del usuario');
        $this->post(route('space-cash.close-user', $space), ['closing_amount' => 95.5])->assertRedirect(route('space-cash.show', $space));
        $this->assertSame('closed', $space->refresh()->status);
        $this->assertSame('95.50', $space->closing_amount);
        $this->assertSame('closed', $pos->refresh()->status);
        $this->assertSame('95.50', $pos->closing_amount);
        $this->post(route('space-cash.close-user', $space), ['closing_amount' => 10])->assertSessionHasErrors('closing_amount');
        $this->assertSame('95.50', $space->refresh()->closing_amount);
    }

    public function test_only_super_admin_can_close_and_only_in_active_company(): void
    {
        [$admin, $owner] = $this->users();
        $legacy = SpaceCashRegister::factory()->create(['company_id' => $owner->company_id, 'user_id' => $owner->id, 'status' => 'open']);
        $this->actingAs($owner)->post(route('space-cash.close-user', $legacy), ['closing_amount' => 10])->assertForbidden();
        $otherCompany = Company::factory()->create();
        $other = SpaceCashRegister::factory()->create(['company_id' => $otherCompany->id, 'user_id' => User::factory()->create(['company_id' => $otherCompany->id])->id, 'status' => 'open']);
        $this->actingAs($admin)->post(route('space-cash.close-user', $other), ['closing_amount' => 10])->assertNotFound();
        $this->post(route('space-cash.close-user', $legacy), ['closing_amount' => -1])->assertSessionHasErrors('closing_amount');
        $this->post(route('space-cash.close-user', $legacy), ['closing_amount' => 20])->assertRedirect();
        $this->assertSame('closed', $legacy->refresh()->status);
    }

    private function users(): array
    {
        foreach (['space-cash.access', 'space-cash.view', 'space-cash.view-all', 'occupancy.manage'] as $permission) {
            Permission::findOrCreate($permission);
        }
        $company = Company::factory()->create();
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->assignRole(Role::findOrCreate('super_admin'));
        $admin->givePermissionTo(['space-cash.access', 'space-cash.view']);
        $owner = User::factory()->create(['company_id' => $company->id]);
        $owner->givePermissionTo(['space-cash.access', 'space-cash.view']);
        return [$admin, $owner];
    }
}
