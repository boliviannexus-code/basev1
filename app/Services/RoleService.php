<?php

namespace App\Services;

use App\Repositories\RoleRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleService
{
    private const ADMIN_ROLES = ['admin', 'super_admin'];

    public function __construct(
        private readonly RoleRepository $roles
    ) {}

    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return $this->roles->paginate($perPage);
    }

    public function create(array $data): Role
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);

        $data['guard_name'] = $data['guard_name'] ?? 'web';

        $role = $this->roles->create($data);
        $this->syncPermissions($role, $permissions);

        Log::info('Role created', ['role_id' => $role->id, 'name' => $role->name]);

        return $this->roles->withUsersCount($role->refresh()->load('permissions')->loadCount('permissions'));
    }

    public function update(Role $role, array $data): Role
    {
        $permissions = $data['permissions'] ?? null;
        unset($data['permissions']);

        if (in_array($role->name, self::ADMIN_ROLES, true) && ($data['name'] ?? $role->name) !== $role->name) {
            throw ValidationException::withMessages([
                'name' => 'No puedes renombrar un rol administrador del sistema.',
            ]);
        }

        $data['guard_name'] = $data['guard_name'] ?? $role->guard_name;

        $role = $this->roles->update($role, $data);

        if (is_array($permissions)) {
            $this->syncPermissions($role, $permissions);
        }

        Log::info('Role updated', ['role_id' => $role->id, 'name' => $role->name]);

        return $this->roles->withUsersCount($role->refresh()->load('permissions')->loadCount('permissions'));
    }

    public function syncPermissions(Role $role, array $permissions): Role
    {
        if ($role->name === 'super_admin') {
            $permissions = Permission::query()->pluck('name')->all();
        }

        if (in_array($role->name, self::ADMIN_ROLES, true)) {
            $required = ['roles.view', 'roles.edit', 'roles.assign-permissions'];

            if (count(array_intersect($required, $permissions)) !== count($required)) {
                throw ValidationException::withMessages([
                    'permissions' => 'El rol administrador debe conservar los permisos para administrar roles.',
                ]);
            }
        }

        $role->syncPermissions($permissions);
        $this->forgetCache();

        Log::warning('Role permissions assigned', ['role_id' => $role->id, 'permissions' => $permissions]);

        return $this->roles->withUsersCount($role->refresh()->load('permissions')->loadCount('permissions'));
    }

    public function delete(Role $role): bool
    {
        if (in_array($role->name, self::ADMIN_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => 'No puedes eliminar un rol administrador del sistema.',
            ]);
        }

        $deleted = $this->roles->delete($role);
        $this->forgetCache();

        Log::warning('Role deleted', ['role_id' => $role->id, 'name' => $role->name]);

        return $deleted;
    }

    private function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
