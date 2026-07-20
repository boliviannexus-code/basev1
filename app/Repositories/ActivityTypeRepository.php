<?php

namespace App\Repositories;

use App\Models\ActivityType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ActivityTypeRepository
{
    public function query(): Builder
    {
        return ActivityType::query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return ActivityType::query()
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): ActivityType
    {
        return ActivityType::query()->create($data);
    }

    public function update(ActivityType $activityType, array $data): ActivityType
    {
        $activityType->update($data);

        return $activityType->refresh();
    }

    public function delete(ActivityType $activityType): bool
    {
        return (bool) $activityType->delete();
    }
}
