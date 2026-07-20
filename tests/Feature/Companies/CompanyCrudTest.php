<?php

namespace Tests\Feature\Companies;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_sports_league_with_report_data_and_logo(): void
    {
        Storage::fake('public');
        $user = $this->userWithCompanyPermissions();

        $this
            ->actingAs($user)
            ->post(route('companies.store'), [
                'name' => 'Liga Demo',
                'code' => 'LDE',
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

        $this->assertSame('Liga Demo', $company->name);
        $this->assertSame('LDE', $company->code);
        $this->assertNotNull($company->logo_path);
        Storage::disk('public')->assertExists($company->logo_path);
    }

    public function test_company_logo_is_rendered_from_storage_in_index_without_public_symlink(): void
    {
        Storage::fake('public');
        $user = $this->userWithCompanyPermissions();
        $company = Company::factory()->create([
            'name' => 'Liga Con Logo',
            'logo_path' => 'companies/logos/demo.png',
        ]);
        Storage::disk('public')->put(
            $company->logo_path,
            file_get_contents(UploadedFile::fake()->image('logo.png')->getRealPath())
        );

        $this
            ->actingAs($user)
            ->get(route('companies.index'))
            ->assertOk()
            ->assertSee('data:image/png;base64', false)
            ->assertDontSee('/storage/companies/logos/demo.png', false);
    }

    public function test_user_can_assign_company_to_user(): void
    {
        $actor = $this->userWithUserPermissions();
        $company = Company::factory()->create(['name' => 'Liga Asignada']);

        $this
            ->actingAs($actor)
            ->post(route('users.store'), [
                'company_id' => $company->id,
                'name' => 'Cajero Liga',
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
            ->assertSee('Liga Asignada');
    }

    private function userWithCompanyPermissions(): User
    {
        $permissions = ['companies.view', 'companies.create', 'companies.update', 'companies.delete'];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        $user = User::factory()->create();
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
