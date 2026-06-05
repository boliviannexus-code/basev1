<?php

namespace App\Services;

use App\Models\Division;
use App\Models\DivisionCategory;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DivisionService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Division::query())
            ->with('company')
            ->withCount(['tournaments', 'categories'])
            ->latest()
            ->paginate($perPage);
    }

    public function forSelect(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Division::query())
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Division
    {
        $data = $this->normalizeCompany($data);
        $data = $this->normalize($data, true);

        return Division::query()->create($data);
    }

    public function update(Division $division, array $data): Division
    {
        $this->ensureVisible($division);

        $shouldSyncCategories = (bool) ($data['sync_categories'] ?? false);
        $categoryIds = collect($data['category_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        unset($data['company_id'], $data['sync_categories'], $data['category_ids']);
        $data = $this->normalize($data);

        DB::transaction(function () use ($division, $data, $shouldSyncCategories, $categoryIds): void {
            $division->update($data);

            if ($shouldSyncCategories) {
                $this->syncCategories($division, $categoryIds->all());
            }
        });

        return $division->refresh();
    }

    public function delete(Division $division): bool
    {
        $this->ensureVisible($division);

        if ($division->tournaments()->exists()) {
            throw ValidationException::withMessages([
                'division' => 'No puedes eliminar una division que ya esta vinculada a torneos.',
            ]);
        }

        return (bool) $division->delete();
    }

    public function ensureVisible(Division $division): void
    {
        abort_unless(CompanyContext::belongsToUser($division->company_id, auth()->user()), 403);
    }

    private function normalizeCompany(array $data): array
    {
        $companyId = CompanyContext::id();

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Selecciona la liga deportiva de la division.',
            ]);
        }

        return $data;
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($defaultActive !== null) {
            $data['is_active'] = $defaultActive;
        }

        return $data;
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function syncCategories(Division $division, array $categoryIds): void
    {
        $selectedCategories = DivisionCategory::query()
            ->where('company_id', $division->company_id)
            ->whereIn('id', $categoryIds)
            ->where('is_active', true)
            ->get();

        if ($selectedCategories->count() !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => 'Selecciona solo categorias registradas en esta liga deportiva.',
            ]);
        }

        $duplicatedName = $selectedCategories
            ->map(fn (DivisionCategory $category): string => mb_strtolower(trim($category->name)))
            ->duplicates()
            ->isNotEmpty();

        if ($duplicatedName) {
            throw ValidationException::withMessages([
                'category_ids' => 'No puedes asociar categorias con nombres repetidos a la misma division.',
            ]);
        }

        $movingUsedCategory = $selectedCategories
            ->filter(fn (DivisionCategory $category): bool => (int) $category->division_id !== (int) $division->id)
            ->filter(fn (DivisionCategory $category): bool => $category->tournaments()->exists())
            ->isNotEmpty();

        if ($movingUsedCategory) {
            throw ValidationException::withMessages([
                'category_ids' => 'No puedes mover una categoria que ya esta vinculada a torneos.',
            ]);
        }

        $categoriesToRemove = $division->categories()
            ->whereNotIn('id', $categoryIds)
            ->get();

        if ($categoriesToRemove->contains(fn (DivisionCategory $category): bool => $category->tournaments()->exists())) {
            throw ValidationException::withMessages([
                'category_ids' => 'No puedes eliminar de la division una categoria que ya esta vinculada a torneos.',
            ]);
        }

        if ($categoriesToRemove->isNotEmpty()) {
            DivisionCategory::query()
                ->whereKey($categoriesToRemove->pluck('id'))
                ->delete();
        }

        if ($categoryIds !== []) {
            DivisionCategory::query()
                ->whereKey($categoryIds)
                ->update(['division_id' => $division->id]);
        }
    }
}
