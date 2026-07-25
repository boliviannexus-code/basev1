<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserSessionSecurityService
{
    public function revokeForUser(User $user, ?string $exceptSessionId = null): void
    {
        $user->tokens()->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();

        if (config('session.driver') === 'database') {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->when($exceptSessionId, fn ($query) => $query->where('id', '!=', $exceptSessionId))
                ->delete();
        }
    }

    public function revokeForCompany(Company $company): void
    {
        $company->users()
            ->select(['id'])
            ->chunkById(200, function ($users): void {
                foreach ($users as $user) {
                    $this->revokeForUser($user);
                }
            });
    }
}
