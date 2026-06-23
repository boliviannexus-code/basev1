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
            'company-public-profile.manage',
            'audits.view',
            'accommodation-catalogs.manage',
            'spaces.view',
            'spaces.create',
            'spaces.edit',
            'spaces.approve',
            'availability.view',
            'availability.manage',
            'occupancy.view',
            'occupancy.manage',
            'reservations.view',
            'reservations.manage',
            'reservation-settings.manage',
            'reservation-channels.manage',
            'extra-charge-categories.manage',
            'countries.manage',
            'exchange-rates.manage',
            'space-cash.access',
            'space-cash.view',
            'pos.access',
            'sales.view',
            'sales.void',
            'payment-methods.view',
            'payment-methods.create',
            'payment-methods.update',
            'payment-methods.delete',
            'point-of-sales.view',
            'point-of-sales.create',
            'point-of-sales.update',
            'point-of-sales.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Permission::query()
            ->whereNotIn('name', $permissions)
            ->delete();

        Role::findOrCreate('super_admin')->syncPermissions($permissions);
        Role::findOrCreate('admin')->syncPermissions(array_diff($permissions, ['accommodation-catalogs.manage', 'spaces.approve']));

        Role::findOrCreate('manager')->syncPermissions([
            'dashboard.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'companies.view',
            'companies.create',
            'companies.update',
            'company-public-profile.manage',
            'audits.view',
            'spaces.view',
            'spaces.create',
            'spaces.edit',
            'availability.view',
            'availability.manage',
            'occupancy.view',
            'occupancy.manage',
            'reservations.view',
            'reservations.manage',
            'reservation-settings.manage',
            'reservation-channels.manage',
            'extra-charge-categories.manage',
            'countries.manage',
            'exchange-rates.manage',
            'space-cash.access',
            'space-cash.view',
            'pos.access',
            'sales.view',
            'sales.void',
            'payment-methods.view',
            'payment-methods.create',
            'payment-methods.update',
            'payment-methods.delete',
            'point-of-sales.view',
            'point-of-sales.create',
            'point-of-sales.update',
            'point-of-sales.delete',
        ]);

        Role::findOrCreate('viewer')->syncPermissions([
            'dashboard.view',
            'users.view',
            'roles.view',
            'permissions.view',
            'companies.view',
            'company-public-profile.manage',
            'audits.view',
            'spaces.view',
            'availability.view',
            'occupancy.view',
            'reservations.view',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
