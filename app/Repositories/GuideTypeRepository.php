<?php

namespace App\Repositories;

use App\Models\GuideType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GuideTypeRepository
{
    public function query(): Builder
    {
        return GuideType::query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return GuideType::query()
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): GuideType
    {
        return GuideType::query()->create($data);
    }

    public function update(GuideType $guideType, array $data): GuideType
    {
        $guideType->update($data);

        return $guideType->refresh();
    }

    public function delete(GuideType $guideType): bool
    {
        return (bool) $guideType->delete();
    }
}
