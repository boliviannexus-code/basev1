<?php

namespace App\Services;

use App\Models\Category;
use App\Repositories\CategoryRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CategoryService
{
    public function __construct(
        private readonly CategoryRepository $categories
    ) {}

    public function query(): Builder
    {
        return $this->categories->query();
    }

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->categories->paginate($perPage);
    }

    public function create(array $data): Category
    {
        return $this->categories->create($this->normalize($data));
    }

    public function update(Category $category, array $data): Category
    {
        return $this->categories->update($category, $this->normalize($data));
    }

    public function delete(Category $category): bool
    {
        return $this->categories->delete($category);
    }

    private function normalize(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
