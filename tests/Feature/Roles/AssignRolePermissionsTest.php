<?php

namespace Tests\Feature\Roles;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AssignRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_labels_are_displayed_in_spanish_without_changing_values(): void
    {
        $actor = User::factory()->create();
        Permission::findOrCreate('roles.assign-permissions');
        Permission::findOrCreate('companies.view');
        Permission::findOrCreate('company-public-profile.manage');
        $actor->givePermissionTo('roles.assign-permissions');

        $role = Role::findOrCreate('manager');
        $role->givePermissionTo('companies.view');

        $response = $this
            ->actingAs($actor)
            ->get(route('roles.permissions.form', $role));

        $response->assertOk();
        $response->assertSee('Gerente');
        $response->assertSee('Empresa');
        $response->assertSee('Consultar la información de este módulo.');
        $response->assertSee('value="companies.view"', false);
        $response->assertSee('Perfil público');
        $response->assertSee('Ver y modificar el perfil público de la empresa.');
        $response->assertSee('value="company-public-profile.manage"', false);
        $response->assertSee('Buscar módulo o permiso');
    }

    public function test_role_permissions_can_be_saved_without_role_name(): void
    {
        $actor = User::factory()->create();
        Permission::findOrCreate('roles.assign-permissions');
        $actor->givePermissionTo('roles.assign-permissions');

        $role = Role::findOrCreate('manager');
        Permission::findOrCreate('companies.view');

        $response = $this
            ->actingAs($actor)
            ->patch(route('roles.permissions', $role), [
                'permissions' => ['companies.view'],
            ]);

        $response->assertRedirect(route('roles.index'));
        $this->assertTrue($role->fresh()->hasPermissionTo('companies.view'));
    }

    public function test_all_company_permissions_can_be_removed_from_super_admin(): void
    {
        $actor = User::factory()->create();
        $requiredPermissions = [
            'roles.assign-permissions',
            'roles.view',
            'roles.edit',
        ];
        $companyPermissions = [
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
        ];

        foreach ([...$requiredPermissions, ...$companyPermissions] as $permission) {
            Permission::findOrCreate($permission);
        }

        $actor->givePermissionTo('roles.assign-permissions');

        $role = Role::findOrCreate('super_admin');
        $role->syncPermissions([...$requiredPermissions, ...$companyPermissions]);

        $response = $this
            ->actingAs($actor)
            ->patch(route('roles.permissions', $role), [
                'permissions' => $requiredPermissions,
            ]);

        $response->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertTrue($role->hasAllPermissions($requiredPermissions));
        $this->assertFalse($role->hasAnyPermission($companyPermissions));
    }
}
