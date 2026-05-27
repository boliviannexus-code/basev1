<?php

namespace App\Services;

use App\Models\Division;
use App\Models\DivisionCategory;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class DivisionCategoryService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(DivisionCategory::query())
            ->with(['company', 'division'])
            ->latest()
            ->paginate($perPage);
    }

    public function forDivisionSelect(int $divisionId): Collection
    {
        return CompanyContext::scope(DivisionCategory::query())
            ->where('division_id', $divisionId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): DivisionCategory
    {
        $data = $this->normalizeDivision($data);
        $this->ensureUniqueInDivision($data['company_id'], $data['division_id'], $data['name']);
        $data = $this->normalize($data, true);

        return DivisionCategory::query()->create($data);
    }

    public function update(DivisionCategory $category, array $data): DivisionCategory
    {
        $this->ensureVisible($category);

        $data = $this->normalizeDivision($data);
        $this->ensureUniqueInDivision($data['company_id'], $data['division_id'], $data['name'], $category->id);
        $data = $this->normalize($data);

        $category->update($data);

        return $category->refresh();
    }

    public function delete(DivisionCategory $category): bool
    {
        $this->ensureVisible($category);

        return (bool) $category->delete();
    }

    public function ensureVisible(DivisionCategory $category): void
    {
        abort_unless(CompanyContext::belongsToUser($category->company_id, auth()->user()), 403);
    }

    private function normalizeDivision(array $data): array
    {
        $companyId = CompanyContext::id();

        $division = Division::query()
            ->whereKey($data['division_id'] ?? null)
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->first();

        if (! $division) {
            throw ValidationException::withMessages([
                'division_id' => 'La division seleccionada no pertenece a la liga deportiva activa.',
            ]);
        }

        $data['company_id'] = $division->company_id;

        return $data;
    }

    private function ensureUniqueInDivision(int $companyId, int $divisionId, string $name, ?int $ignoreId = null): void
    {
        $exists = DivisionCategory::query()
            ->where('company_id', $companyId)
            ->where('division_id', $divisionId)
            ->whereRaw('LOWER(name) = LOWER(?)', [$name])
            ->whereNull('deleted_at')
            ->when($ignoreId, fn ($query, $ignoreId) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'Esta categoria ya esta registrada en la division seleccionada.',
            ]);
        }
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
}
