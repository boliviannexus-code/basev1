<?php

namespace App\Services;

use App\Models\TransportType;
use App\Repositories\TransportTypeRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TransportTypeService
{
    public function __construct(
        private readonly TransportTypeRepository $transportTypes
    ) {}

    public function query(): Builder
    {
        return $this->transportTypes->query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->transportTypes->paginate($perPage);
    }

    public function create(array $data): TransportType
    {
        return $this->transportTypes->create($data);
    }

    public function update(TransportType $transportType, array $data): TransportType
    {
        return $this->transportTypes->update($transportType, $data);
    }

    public function delete(TransportType $transportType): bool
    {
        return $this->transportTypes->delete($transportType);
    }
}
