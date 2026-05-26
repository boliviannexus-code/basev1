<?php

namespace App\Services;

use App\Models\GuideType;
use App\Repositories\GuideTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GuideTypeService
{
    public function __construct(
        private readonly GuideTypeRepository $guideTypes
    ) {}

    public function query(): Builder
    {
        return $this->guideTypes->query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->guideTypes->paginate($perPage);
    }

    public function create(array $data): GuideType
    {
        return $this->guideTypes->create($data);
    }

    public function update(GuideType $guideType, array $data): GuideType
    {
        return $this->guideTypes->update($guideType, $data);
    }

    public function delete(GuideType $guideType): bool
    {
        return $this->guideTypes->delete($guideType);
    }
}
