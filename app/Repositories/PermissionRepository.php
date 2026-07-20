<?php

namespace App\Repositories;

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
        $moduleOrder = collect([
            'dashboard',
            'users',
            'roles',
            'permissions',
            'fingerprint-templates',
            'companies',
            'seasons',
            'divisions',
            'courts',
            'teams',
            'players',
            'player-imports',
            'categories',
            'tournaments',
            'tournament-registrations',
            'fixtures',
            'matchdays',
            'match-reports',
            'meetings',
            'standings',
            'accreditations',
            'player-habilitations',
            'player-transfers',
            'league-settings',
            'audits',
        ])->flip();

        return Permission::query()
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Permission $permission): string => str($permission->name)->before('.')->toString())
            ->sortKeysUsing(fn (string $left, string $right): int => ($moduleOrder[$left] ?? 999) <=> ($moduleOrder[$right] ?? 999));
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
