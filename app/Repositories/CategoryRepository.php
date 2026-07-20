<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CategoryRepository
{
    public function query(): Builder
    {
        return Category::query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Category::query()
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data);
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): bool
    {
        return (bool) $category->delete();
    }
}
