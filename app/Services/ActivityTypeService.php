<?php

namespace App\Services;

use App\Models\ActivityType;
use App\Repositories\ActivityTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ActivityTypeService
{
    public function __construct(
        private readonly ActivityTypeRepository $activityTypes
    ) {}

    public function query(): Builder
    {
        return $this->activityTypes->query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->activityTypes->paginate($perPage);
    }

    public function create(array $data): ActivityType
    {
        return $this->activityTypes->create($this->normalize($data));
    }

    public function update(ActivityType $activityType, array $data): ActivityType
    {
        return $this->activityTypes->update($activityType, $this->normalize($data));
    }

    public function delete(ActivityType $activityType): bool
    {
        return $this->activityTypes->delete($activityType);
    }

    private function normalize(array $data): array
    {
        $data['slug'] = Str::slug($data['slug']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
