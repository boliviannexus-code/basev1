<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyOnlineStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_super_admin_can_enable_company_online_status(): void
    {
        $admin = $this->globalSuperAdmin();
        $company = Company::factory()->create([
            'is_online_enabled_by_admin' => false,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.companies.enable-online', $company))
            ->assertRedirect()
            ->assertSessionHas('success', 'Empresa habilitada para página pública online.');

        $this->assertTrue($company->fresh()->is_online_enabled_by_admin);
    }

    public function test_global_super_admin_can_disable_company_online_status_without_deleting_public_data(): void
    {
        $admin = $this->globalSuperAdmin();
        $company = Company::factory()->create([
            'public_slug' => 'demo-online',
            'public_name' => 'Demo Online',
            'is_public_enabled' => true,
            'is_online_enabled_by_admin' => true,
        ]);

        $this
            ->actingAs($admin)
            ->patch(route('admin.companies.disable-online', $company))
            ->assertRedirect()
            ->assertSessionHas('success', 'Empresa deshabilitada para página pública online.');

        $company->refresh();

        $this->assertFalse($company->is_online_enabled_by_admin);
        $this->assertTrue($company->is_public_enabled);
        $this->assertSame('demo-online', $company->public_slug);
        $this->assertSame('Demo Online', $company->public_name);
    }

    public function test_company_user_cannot_change_company_online_status(): void
    {
        $company = Company::factory()->create([
            'is_online_enabled_by_admin' => false,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
        ]);
        $user->assignRole(Role::findOrCreate('super_admin'));

        $this
            ->actingAs($user)
            ->patch(route('admin.companies.enable-online', $company))
            ->assertForbidden();

        $this->assertFalse($company->fresh()->is_online_enabled_by_admin);
    }

    private function globalSuperAdmin(): User
    {
        $user = User::factory()->create([
            'company_id' => null,
        ]);

        $user->assignRole(Role::findOrCreate('super_admin'));

        return $user;
    }
}
