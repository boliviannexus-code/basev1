<?php

namespace App\Repositories;

use App\Models\TransportType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TransportTypeRepository
{
    public function query(): Builder
    {
        return TransportType::query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return TransportType::query()
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): TransportType
    {
        return TransportType::query()->create($data);
    }

    public function update(TransportType $transportType, array $data): TransportType
    {
        $transportType->update($data);

        return $transportType->refresh();
    }

    public function delete(TransportType $transportType): bool
    {
        return (bool) $transportType->delete();
    }
}
