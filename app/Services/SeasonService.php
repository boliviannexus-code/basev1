<?php

namespace App\Services;

use App\Models\Season;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class SeasonService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Season::query())
            ->with('company')
            ->withCount('tournaments')
            ->latest()
            ->paginate($perPage);
    }

    public function forSelect(?int $companyId = null): Collection
    {
        return CompanyContext::scope(Season::query())
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->where('is_active', true)
            ->orderByDesc('year')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Season
    {
        $data = $this->normalizeCompany($data);
        $data = $this->normalize($data, true);

        return Season::query()->create($data);
    }

    public function update(Season $season, array $data): Season
    {
        $this->ensureVisible($season);

        unset($data['company_id']);
        $data = $this->normalize($data);

        $season->update($data);

        return $season->refresh();
    }

    public function delete(Season $season): bool
    {
        $this->ensureVisible($season);

        return (bool) $season->delete();
    }

    public function finish(Season $season): Season
    {
        $this->ensureVisible($season);

        $season->forceFill([
            'status' => 'closed',
            'is_active' => false,
        ])->save();

        return $season->refresh();
    }

    public function ensureVisible(Season $season): void
    {
        abort_unless(CompanyContext::belongsToUser($season->company_id, auth()->user()), 403);
    }

    private function normalizeCompany(array $data): array
    {
        $companyId = CompanyContext::id();

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Selecciona la liga deportiva de la gestion.',
            ]);
        }

        return $data;
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        $data['status'] = $data['status'] ?? 'active';
        $data['is_active'] = $data['status'] === 'active';

        return $data;
    }
}
