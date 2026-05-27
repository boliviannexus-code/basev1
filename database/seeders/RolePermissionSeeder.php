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
            'fingerprint-templates.view',
            'fingerprint-templates.create',
            'fingerprint-templates.update',
            'fingerprint-templates.delete',
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
            'seasons.view',
            'seasons.create',
            'seasons.update',
            'seasons.delete',
            'divisions.view',
            'divisions.create',
            'divisions.update',
            'divisions.delete',
            'teams.view',
            'teams.create',
            'teams.update',
            'teams.delete',
            'teams.approve-updates',
            'tournaments.view',
            'tournaments.create',
            'tournaments.update',
            'tournaments.delete',
            'tournament-registrations.view',
            'tournament-registrations.create',
            'tournament-registrations.update',
            'tournament-registrations.delete',
            'categories.view',
            'categories.create',
            'categories.update',
            'categories.delete',
            'audits.view',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission);
        }

        Permission::query()
            ->whereNotIn('name', $permissions)
            ->delete();

        Role::findOrCreate('super_admin')->syncPermissions($permissions);
        Role::findOrCreate('admin')->syncPermissions(array_values(array_diff($permissions, ['teams.approve-updates'])));

        Role::findOrCreate('manager')->syncPermissions([
            'dashboard.view',
            'users.view',
            'fingerprint-templates.view',
            'fingerprint-templates.create',
            'fingerprint-templates.update',
            'roles.view',
            'permissions.view',
            'companies.view',
            'companies.create',
            'companies.update',
            'seasons.view',
            'seasons.create',
            'seasons.update',
            'divisions.view',
            'divisions.create',
            'divisions.update',
            'teams.view',
            'teams.create',
            'teams.update',
            'tournaments.view',
            'tournaments.create',
            'tournaments.update',
            'tournament-registrations.view',
            'tournament-registrations.create',
            'tournament-registrations.update',
            'categories.view',
            'categories.create',
            'categories.update',
            'audits.view',
        ]);

        Role::findOrCreate('viewer')->syncPermissions([
            'dashboard.view',
            'users.view',
            'fingerprint-templates.view',
            'roles.view',
            'permissions.view',
            'companies.view',
            'seasons.view',
            'divisions.view',
            'teams.view',
            'tournaments.view',
            'tournament-registrations.view',
            'categories.view',
            'audits.view',
        ]);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
