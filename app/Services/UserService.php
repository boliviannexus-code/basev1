<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        private readonly UserRepository $users
    ) {}

    public function paginate(int $perPage = 15, bool $withTrashed = false): LengthAwarePaginator
    {
        return $this->users->paginate($perPage, $withTrashed);
    }

    public function findWithTrashed(int|string $id): User
    {
        return $this->users->findWithTrashed($id);
    }

    public function create(array $data): User
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $data = $this->applyCompanyAssignmentRules($data);
        $data['password'] = Hash::make($data['password']);
        if (filled($data['transaction_pin'] ?? null)) {
            $data['transaction_pin'] = Hash::make((string) $data['transaction_pin']);
        } else {
            unset($data['transaction_pin']);
        }
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        $user = $this->users->create($data);
        $user->syncRoles($roles);

        Log::info('User created', ['user_id' => $user->id, 'roles' => $roles]);

        return $user->refresh()->load('roles');
    }

    public function update(User $user, array $data): User
    {
        $roles = $data['roles'] ?? null;
        unset($data['roles'], $data['password']);

        $data = $this->applyCompanyAssignmentRules($data);
        if (filled($data['transaction_pin'] ?? null)) {
            $data['transaction_pin'] = Hash::make((string) $data['transaction_pin']);
        } else {
            unset($data['transaction_pin']);
        }
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $user->is_active;

        if ($user->is_active && ! $data['is_active']) {
            $this->ensureCanDeactivate($user);
        }

        $user = $this->users->update($user, $data);

        if (is_array($roles)) {
            $this->syncRoles($user, $roles);
        }

        return $user->refresh()->load('roles');
    }

    public function toggleStatus(User $user): User
    {
        if ($user->is_active) {
            $this->ensureCanDeactivate($user);
        }

        $user = $this->users->update($user, [
            'is_active' => ! $user->is_active,
        ]);

        Log::warning('User status toggled', ['user_id' => $user->id, 'is_active' => $user->is_active]);

        return $user;
    }

    public function changePassword(User $user, string $password): User
    {
        $user = $this->users->update($user, [
            'password' => Hash::make($password),
        ]);

        Log::warning('User password changed', ['user_id' => $user->id]);

        return $user;
    }

    public function syncRoles(User $user, array $roles): User
    {
        $removingAdminRole = $user->hasAnyRole(['admin', 'super_admin'])
            && empty(array_intersect($roles, ['admin', 'super_admin']));

        if ($removingAdminRole && $user->is_active && $this->users->activeAdminsExcluding($user) === 0) {
            throw ValidationException::withMessages([
                'roles' => 'No puedes quitar el rol administrativo al ultimo admin activo.',
            ]);
        }

        $user->syncRoles($roles);

        Log::warning('User roles assigned', ['user_id' => $user->id, 'roles' => $roles]);

        return $user->refresh()->load('roles');
    }

    public function delete(User $user, ?User $actor = null): bool
    {
        if ($actor && $actor->is($user) && $user->hasAnyRole(['admin', 'super_admin']) && $this->users->activeAdminsCount() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminarte si eres el unico admin activo.',
            ]);
        }

        if ($user->is_active && $user->hasAnyRole(['admin', 'super_admin']) && $this->users->activeAdminsExcluding($user) === 0) {
            throw ValidationException::withMessages([
                'user' => 'No puedes eliminar al ultimo admin activo.',
            ]);
        }

        $deleted = $this->users->delete($user);

        Log::warning('User deleted', ['user_id' => $user->id, 'actor_id' => $actor?->id]);

        return $deleted;
    }

    public function restore(User $user): User
    {
        $this->users->restore($user);

        Log::warning('User restored', ['user_id' => $user->id]);

        return $user->refresh()->load('roles');
    }

    private function ensureCanDeactivate(User $user): void
    {
        if ($user->hasAnyRole(['admin', 'super_admin']) && $this->users->activeAdminsExcluding($user) === 0) {
            throw ValidationException::withMessages([
                'user' => 'No puedes desactivar al ultimo admin activo.',
            ]);
        }
    }

    private function applyCompanyAssignmentRules(array $data): array
    {
        $actor = auth()->user();
        $assigningNoCompany = array_key_exists('company_id', $data) && blank($data['company_id']);

        if ($assigningNoCompany && CompanyContext::canAssignNoCompany($actor)) {
            $data['company_id'] = null;

            return $data;
        }

        return CompanyContext::applyToData($data, $actor);
    }
}
