<?php

namespace App\Services;

use App\Models\Court;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class CourtService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Court::query())
            ->with('company')
            ->withCount('matchdayDates')
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function activeForCompany(int $companyId): Collection
    {
        return CompanyContext::scope(Court::query())
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Court
    {
        $data = $this->normalizeCompany($data);
        $data = $this->normalize($data, true);

        return Court::query()->create($data);
    }

    public function update(Court $court, array $data): Court
    {
        $this->ensureVisible($court);

        unset($data['company_id']);
        $court->update($this->normalize($data));

        return $court->refresh();
    }

    public function delete(Court $court): bool
    {
        $this->ensureVisible($court);

        if ($court->matchdayDates()->exists()) {
            throw ValidationException::withMessages([
                'court' => 'No puedes eliminar una cancha vinculada a jornadas.',
            ]);
        }

        return (bool) $court->delete();
    }

    public function ensureVisible(Court $court): void
    {
        abort_unless(CompanyContext::belongsToUser($court->company_id, auth()->user()), 403);
    }

    private function normalizeCompany(array $data): array
    {
        $companyId = CompanyContext::id();

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Selecciona la liga deportiva de la cancha.',
            ]);
        }

        if (! CompanyContext::belongsToUser((int) $data['company_id'], auth()->user())) {
            throw ValidationException::withMessages([
                'company_id' => 'No puedes registrar canchas en esta liga.',
            ]);
        }

        return $data;
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['address'] = filled($data['address'] ?? null) ? trim((string) $data['address']) : null;
        $data['description'] = filled($data['description'] ?? null) ? trim((string) $data['description']) : null;

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($defaultActive !== null) {
            $data['is_active'] = $defaultActive;
        }

        return $data;
    }
}
