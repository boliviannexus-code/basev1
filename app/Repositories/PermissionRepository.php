<?php

namespace App\Repositories;

use App\Support\PermissionCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Models\Permission;

class PermissionRepository
{
    public function paginate(int $perPage = 20): LengthAwarePaginator
    {
        return Permission::query()
            ->latest()
            ->paginate($perPage);
    }

    public function allGroupedByModule(): Collection
    {
        return Permission::query()
            ->get()
            ->sortBy(fn (Permission $permission): string => sprintf(
                '%04d-%s',
                PermissionCatalog::moduleOrder(str($permission->name)->before('.')->toString()),
                $permission->name
            ))
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString());
    }

    public function create(array $data): Permission
    {
        return Permission::create($data);
    }

    public function update(Permission $permission, array $data): Permission
    {
        $permission->update($data);

        return $permission->refresh();
    }

    public function delete(Permission $permission): bool
    {
        return (bool) $permission->delete();
    }
}
