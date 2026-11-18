<?php

namespace App\Services\Occupancy;

use App\Models\Stay;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomCleaningService
{
    public static function pending(int $companyId): Builder
    {
        return Stay::query()->where('company_id', $companyId)
            ->where('status', 'checked_out')
            ->whereNotNull('cleaning_required_at')->whereNull('cleaned_at');
    }

    public static function forResource(Collection $tasks, int $spaceId, ?int $roomId, ?int $bedUnitId): ?Stay
    {
        return $tasks->first(fn (Stay $task): bool => $task->status === 'checked_out'
            && $task->cleaning_required_at !== null && $task->cleaned_at === null
            && (int) $task->space_id === $spaceId
            && (int) $task->space_room_id === (int) $roomId
            && ($bedUnitId === null || $task->room_bed_unit_id === null || (int) $task->room_bed_unit_id === $bedUnitId));
    }

    public function ensureClean(int $companyId, int $spaceId, ?int $roomId, ?int $bedUnitId): void
    {
        $tasks = self::pending($companyId)->where('space_id', $spaceId)->lockForUpdate()->get();
        if (self::forResource($tasks, $spaceId, $roomId, $bedUnitId)) {
            throw ValidationException::withMessages([
                'stays' => 'La habitación está libre y sucia. Debes marcarla como limpia y disponible antes de realizar el check-in.',
            ]);
        }
    }

    public function markClean(Stay $stay, User $user): void
    {
        abort_unless((int) $stay->company_id === (int) $user->company_id, 404);
        abort_unless($user->can('occupancy.manage'), 403);
        DB::transaction(function () use ($stay, $user): void {
            $locked = Stay::query()->lockForUpdate()->findOrFail($stay->id);
            if ($locked->status !== 'checked_out' || ! $locked->cleaning_required_at) {
                throw ValidationException::withMessages(['cleaning' => 'Solo se puede limpiar un espacio con checkout y limpieza pendiente.']);
            }
            if ($locked->cleaned_at) {
                return;
            }
            $locked->update(['cleaned_at' => now(), 'cleaned_by' => $user->id]);
        });
    }
}
