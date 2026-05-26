<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $guard = 'web';

        $permissions = [
            'dashboard.view',
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.restore',
            'users.change-password',
            'users.assign-roles',
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',
            'roles.assign-permissions',
            'permissions.view',
            'permissions.create',
            'permissions.edit',
            'permissions.delete',
            'companies.view',
            'companies.create',
            'companies.update',
            'companies.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'guide_types.view',
            'guide_types.create',
            'guide_types.update',
            'guide_types.delete',
            'transport_types.view',
            'transport_types.create',
            'transport_types.update',
            'transport_types.delete',
            'activity_types.view',
            'activity_types.create',
            'activity_types.update',
            'activity_types.delete',
            'tours.view',
            'tours.create',
            'tours.edit',
            'tours.delete',
            'tours.review',
            'tours.pricing',
            'tours.availability',
            'bookings.view',
            'bookings.manage',
            'website.manage',
            'audits.view',
        ];

        foreach ($permissions as $permission) {
            Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        Permission::query()
            ->whereNotIn('name', $permissions)
            ->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissionModels = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $permissions)
            ->get()
            ->keyBy('name');

        Role::findOrCreate('super_admin', $guard)->syncPermissions($permissionModels->values());
        Role::findOrCreate('admin', $guard)->syncPermissions($permissionModels->values());

        $managerPermissions = [
            'dashboard.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'companies.view',
            'companies.create',
            'companies.update',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'guide_types.view',
            'guide_types.create',
            'guide_types.update',
            'guide_types.delete',
            'transport_types.view',
            'transport_types.create',
            'transport_types.update',
            'transport_types.delete',
            'activity_types.view',
            'activity_types.create',
            'activity_types.update',
            'activity_types.delete',
            'tours.view',
            'tours.create',
            'tours.edit',
            'tours.delete',
            'tours.pricing',
            'tours.availability',
            'bookings.view',
            'bookings.manage',
            'website.manage',
            'audits.view',
        ];

        Role::findOrCreate('manager', $guard)->syncPermissions($permissionModels->only($managerPermissions)->values());

        $viewerPermissions = [
            'dashboard.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'companies.view',
            'categories.view',
            'guide_types.view',
            'transport_types.view',
            'activity_types.view',
            'tours.view',
            'bookings.view',
            'audits.view',
        ];

        Role::findOrCreate('viewer', $guard)->syncPermissions($permissionModels->only($viewerPermissions)->values());

        Role::findOrCreate('tourist', $guard)->syncPermissions([]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
