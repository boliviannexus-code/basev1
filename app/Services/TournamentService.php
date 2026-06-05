<?php

namespace App\Services;

use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Season;
use App\Models\Tournament;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class TournamentService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Tournament::query())
            ->with(['company', 'season', 'division', 'category'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Tournament
    {
        $data = $this->normalizeCompanySeasonDivisionCategory($data);
        $data = $this->normalize($data, true);

        return Tournament::query()->create($data);
    }

    public function update(Tournament $tournament, array $data): Tournament
    {
        $this->ensureVisible($tournament);

        $data['company_id'] = $tournament->company_id;
        $data = $this->normalizeCompanySeasonDivisionCategory($data, $tournament->category_id);
        $data = $this->normalize($data);

        $tournament->update($data);

        return $tournament->refresh();
    }

    public function delete(Tournament $tournament): bool
    {
        $this->ensureVisible($tournament);

        return (bool) $tournament->delete();
    }

    public function ensureVisible(Tournament $tournament): void
    {
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);
    }

    private function normalizeCompanySeasonDivisionCategory(array $data, ?int $allowedInactiveCategoryId = null): array
    {
        $companyId = CompanyContext::id();

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Selecciona la liga deportiva del torneo.',
            ]);
        }

        $season = Season::query()
            ->whereKey($data['season_id'] ?? null)
            ->where('company_id', $data['company_id'])
            ->first();

        if (! $season) {
            throw ValidationException::withMessages([
                'season_id' => 'La gestion seleccionada no pertenece a la liga deportiva activa.',
            ]);
        }

        $division = Division::query()
            ->whereKey($data['division_id'] ?? null)
            ->where('company_id', $data['company_id'])
            ->first();

        if (! $division) {
            throw ValidationException::withMessages([
                'division_id' => 'La division seleccionada no pertenece a la liga deportiva activa.',
            ]);
        }

        $category = DivisionCategory::query()
            ->whereKey($data['category_id'] ?? null)
            ->where('company_id', $data['company_id'])
            ->where('division_id', $division->id)
            ->where(function ($query) use ($allowedInactiveCategoryId): void {
                $query->where('is_active', true)
                    ->when($allowedInactiveCategoryId, fn ($query, $categoryId) => $query->orWhere('id', $categoryId));
            })
            ->first();

        if (! $category) {
            throw ValidationException::withMessages([
                'category_id' => 'La categoria seleccionada no pertenece a la division indicada.',
            ]);
        }

        $data['name'] = $this->buildName($division, $category, $season);

        return $data;
    }

    private function buildName(Division $division, DivisionCategory $category, Season $season): string
    {
        return trim($division->name.' - '.$category->name.' - '.$season->name);
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($defaultActive !== null) {
            $data['is_active'] = $defaultActive;
        }

        $data['status'] = $data['status'] ?? 'planned';

        return $data;
    }
}
