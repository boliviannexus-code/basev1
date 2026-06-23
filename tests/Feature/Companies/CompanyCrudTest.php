<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_company_with_report_data_and_logo(): void
    {
        Storage::fake('public');
        $user = $this->userWithCompanyPermissions();

        $this
            ->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Empresa Demo',
                'legal_name' => 'Empresa Demo SRL',
                'tax_id' => '1234567',
                'phone' => '70000000',
                'email' => 'demo@example.com',
                'address' => 'Av. Siempre Viva',
                'city' => 'La Paz',
                'country' => 'Bolivia',
                'report_footer' => 'Gracias por su compra',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'is_active' => '1',
            ])
            ->assertRedirect(route('companies.index'));

        $company = Company::query()->firstOrFail();

        $this->assertSame('Empresa Demo', $company->name);
        $this->assertSame('Empresa Demo SRL', $company->legal_name);
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_user_can_assign_company_to_user(): void
    {
        $actor = $this->userWithUserPermissions();
        $company = Company::factory()->create(['name' => 'Empresa Asignada']);

        $this
            ->actingAs($actor)
            ->post(route('users.store'), [
                'company_id' => $company->id,
                'name' => 'Cajero Empresa',
                'email' => 'cashier@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'is_active' => '1',
            ])
            ->assertRedirect(route('users.index'));

        $this->assertDatabaseHas('users', [
            'company_id' => $company->id,
            'email' => 'cashier@example.com',
        ]);

        $this
            ->actingAs($actor)
            ->get(route('users.index'))
            ->assertOk()
            ->assertSee('Empresa Asignada');
    }

    public function test_company_admin_only_sees_and_accesses_its_assigned_company(): void
    {
        Permission::findOrCreate('companies.view');
        Permission::findOrCreate('companies.update');

        $company = Company::factory()->create(['name' => 'Empresa propia']);
        $otherCompany = Company::factory()->create(['name' => 'Empresa ajena']);
        $admin = User::factory()->create(['company_id' => $company->id]);
        $admin->givePermissionTo(['companies.view', 'companies.update']);

        $this->assertFalse(CompanyContext::isGlobalAdmin($admin));

        $this
            ->actingAs($admin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('Empresa propia')
            ->assertDontSee('Empresa ajena');

        $this
            ->actingAs($admin)
            ->get(route('companies.show', $otherCompany))
            ->assertNotFound();

        $this
            ->actingAs($admin)
            ->get(route('companies.edit', $otherCompany))
            ->assertNotFound();
    }

    public function test_only_super_admin_without_company_sees_all_companies(): void
    {
        Permission::findOrCreate('companies.view');
        Role::findOrCreate('super_admin')->givePermissionTo('companies.view');

        $firstCompany = Company::factory()->create(['name' => 'Empresa uno']);
        $secondCompany = Company::factory()->create(['name' => 'Empresa dos']);
        $superAdmin = User::factory()->create(['company_id' => null]);
        $superAdmin->assignRole('super_admin');

        $this->assertTrue(CompanyContext::isGlobalAdmin($superAdmin));

        $this
            ->actingAs($superAdmin)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee($firstCompany->name)
            ->assertSee($secondCompany->name);
    }

    private function userWithCompanyPermissions(): User
    {
        $permissions = ['companies.view', 'companies.create', 'companies.update', 'companies.delete'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        Role::findOrCreate('super_admin')->givePermissionTo($permissions);
        $user->assignRole('super_admin');
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function userWithUserPermissions(): User
    {
        $permissions = ['users.view', 'users.create'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
        Role::findOrCreate('super_admin')->givePermissionTo($permissions);
        $user->assignRole('super_admin');
        $user->givePermissionTo($permissions);

        return $user;
    }
}
