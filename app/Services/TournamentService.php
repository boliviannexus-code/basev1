<?php

namespace App\Services;

use App\Models\Division;
use App\Models\DivisionCategory;
use App\Models\Season;
use App\Models\Tournament;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Tournament::query())
            ->with(['company', 'season', 'division', 'categories'])
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Tournament
    {
        $data = $this->normalizeCompanySeasonDivision($data);
        $data = $this->normalize($data, true);

        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);
        $data['category_id'] = $categoryIds[0] ?? null;

        return DB::transaction(function () use ($data, $categoryIds): Tournament {
            $tournament = Tournament::query()->create($data);
            $tournament->categories()->sync($categoryIds);

            return $tournament->refresh();
        });
    }

    public function update(Tournament $tournament, array $data): Tournament
    {
        $this->ensureEditable($tournament);

        $data['company_id'] = $tournament->company_id;
        $data = $this->normalizeCompanySeasonDivision($data);
        $data = $this->normalize($data);

        $categoryIds = $data['category_ids'];
        unset($data['category_ids']);
        $data['category_id'] = $categoryIds[0] ?? null;

        return DB::transaction(function () use ($tournament, $data, $categoryIds): Tournament {
            $tournament->update($data);
            $tournament->categories()->sync($categoryIds);

            return $tournament->refresh();
        });
    }

    public function delete(Tournament $tournament): bool
    {
        $this->ensureVisible($tournament);

        return (bool) $tournament->delete();
    }

    public function activate(Tournament $tournament): Tournament
    {
        $this->ensureVisible($tournament);

        $tournament->forceFill([
            'status' => 'active',
            'is_active' => true,
        ])->save();

        return $tournament->refresh();
    }

    public function finish(Tournament $tournament): Tournament
    {
        $this->ensureVisible($tournament);

        $tournament->forceFill([
            'status' => 'closed',
            'is_active' => false,
        ])->save();

        return $tournament->refresh();
    }

    public function ensureVisible(Tournament $tournament): void
    {
        abort_unless(CompanyContext::belongsToUser($tournament->company_id, auth()->user()), 403);
    }

    public function ensureEditable(Tournament $tournament): void
    {
        $this->ensureVisible($tournament);

        abort_unless($tournament->status === 'planned', 403);
    }

    private function normalizeCompanySeasonDivision(array $data): array
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

        $data['name'] = str((string) ($data['name'] ?? ''))->squish()->toString();
        $data['category_ids'] = $this->validCategoryIds($data, $division);

        return $data;
    }

    private function validCategoryIds(array $data, Division $division): array
    {
        $categoryIds = collect($data['category_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            throw ValidationException::withMessages([
                'category_ids' => 'Selecciona al menos una categoria del torneo.',
            ]);
        }

        $validCount = DivisionCategory::query()
            ->where('company_id', $data['company_id'])
            ->where('division_id', $division->id)
            ->whereIn('id', $categoryIds)
            ->whereNull('deleted_at')
            ->count();

        if ($validCount !== $categoryIds->count()) {
            throw ValidationException::withMessages([
                'category_ids' => 'Selecciona solo categorias de la division indicada.',
            ]);
        }

        return $categoryIds->all();
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        $data['status'] = $data['status'] ?? 'planned';
        $data['is_active'] = $data['status'] === 'active';

        return $data;
    }
}
