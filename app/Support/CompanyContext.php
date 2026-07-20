<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CompanyContext
{
    public static function id(?User $user = null): ?int
    {
        $user ??= auth()->user();
        $tenantCompany = self::tenantCompany();

        if ($tenantCompany !== null) {
            if ($user === null || self::isGlobalAdmin($user) || (int) $user->company_id === (int) $tenantCompany->id) {
                return (int) $tenantCompany->id;
            }

            return -1;
        }

        if ($user === null) {
            return null;
        }

        if (self::isGlobalAdmin($user)) {
            return null;
        }

        return $user->company_id !== null ? (int) $user->company_id : -1;
    }

    public static function canOperate(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && ($user->company_id !== null || self::isGlobalAdmin($user));
    }

    public static function isGlobalAdmin(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null
            && $user->company_id === null
            && $user->hasRole('super_admin');
    }

    public static function canAssignNoCompany(?User $user = null): bool
    {
        $user ??= auth()->user();

        return $user !== null && $user->hasRole('super_admin');
    }

    public static function activeCompany(?User $user = null): ?Company
    {
        $user ??= auth()->user();
        $tenantCompany = self::tenantCompany();

        if ($tenantCompany !== null) {
            if ($user === null || self::isGlobalAdmin($user) || (int) $user->company_id === (int) $tenantCompany->id) {
                return $tenantCompany;
            }

            return null;
        }

        if ($user === null || $user->company_id === null) {
            return null;
        }

        return $user->relationLoaded('company')
            ? $user->company
            : $user->company()->first();
    }

    public static function scope(Builder $query, ?User $user = null, string $column = 'company_id'): Builder
    {
        $companyId = self::id($user);

        return $query->when($companyId, fn (Builder $query): Builder => $query->where($column, $companyId));
    }

    public static function belongsToUser(?int $companyId, User $user): bool
    {
        $tenantCompany = self::tenantCompany();

        if ($tenantCompany !== null && (int) $tenantCompany->id !== (int) $companyId) {
            return false;
        }

        return self::isGlobalAdmin($user)
            || ($user->company_id !== null && (int) $user->company_id === (int) $companyId);
    }

    public static function applyToData(array $data, ?User $user = null): array
    {
        $companyId = self::id($user);

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        return $data;
    }

    public static function tenantCompany(): ?Company
    {
        return app()->bound('tenant.company') ? app('tenant.company') : null;
    }
}
