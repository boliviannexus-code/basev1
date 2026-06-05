<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamUpdateRequest;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Team::query())
            ->with(['company', 'pendingUpdateRequest'])
            ->latest()
            ->paginate($perPage);
    }

    public function pendingApprovals(int $perPage = 15): LengthAwarePaginator
    {
        return TeamUpdateRequest::query()
            ->with(['team.company', 'requester'])
            ->where('status', TeamUpdateRequest::STATUS_PENDING)
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Team
    {
        $data = $this->normalizeCompany($data);
        $data = $this->normalize($data, true);

        return Team::query()->create($data);
    }

    public function update(Team $team, array $data): TeamUpdateRequest
    {
        $this->ensureVisible($team);
        $data = $this->normalize($data);

        if ($team->pendingUpdateRequest()->exists()) {
            throw ValidationException::withMessages([
                'team' => 'Este equipo ya tiene una solicitud de edicion pendiente de aprobacion.',
            ]);
        }

        return TeamUpdateRequest::query()->create([
            'team_id' => $team->id,
            'requested_by' => auth()->id(),
            'original_data' => $this->dataSnapshot($team),
            'proposed_data' => $data,
            'status' => TeamUpdateRequest::STATUS_PENDING,
        ]);
    }

    public function approve(TeamUpdateRequest $request, int $approverId, ?string $notes = null): TeamUpdateRequest
    {
        abort_unless($request->status === TeamUpdateRequest::STATUS_PENDING, 404);

        return DB::transaction(function () use ($request, $approverId, $notes): TeamUpdateRequest {
            $request->load('team');

            $duplicate = Team::query()
                ->where('company_id', $request->team->company_id)
                ->where('name_normalized', Team::normalizeName($request->proposed_data['name']))
                ->whereKeyNot($request->team_id)
                ->whereNull('deleted_at')
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'name' => 'Ya existe otro equipo con ese nombre en la misma liga deportiva.',
                ]);
            }

            $request->team->update($this->normalize($request->proposed_data));
            $request->update([
                'approved_by' => $approverId,
                'status' => TeamUpdateRequest::STATUS_APPROVED,
                'review_notes' => $notes,
                'reviewed_at' => now(),
            ]);

            return $request->refresh();
        });
    }

    public function reject(TeamUpdateRequest $request, int $approverId, ?string $notes = null): TeamUpdateRequest
    {
        abort_unless($request->status === TeamUpdateRequest::STATUS_PENDING, 404);

        $request->update([
            'approved_by' => $approverId,
            'status' => TeamUpdateRequest::STATUS_REJECTED,
            'review_notes' => $notes,
            'reviewed_at' => now(),
        ]);

        return $request->refresh();
    }

    public function delete(Team $team): bool
    {
        $this->ensureVisible($team);

        return (bool) $team->delete();
    }

    public function matches(?string $name, ?int $companyId = null, ?int $ignoreId = null): Collection
    {
        $name = trim((string) $name);

        if (mb_strlen($name) < 2) {
            return new Collection;
        }

        $companyId = CompanyContext::id() ?? $companyId;

        $normalizedName = Team::normalizeName($name);

        return CompanyContext::scope(Team::query())
            ->when($companyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($ignoreId, fn ($query, $ignoreId) => $query->whereKeyNot($ignoreId))
            ->where('name_normalized', 'like', '%'.$normalizedName.'%')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'company_id', 'name', 'founded_at']);
    }

    public function ensureVisible(Team $team): void
    {
        abort_unless(CompanyContext::belongsToUser($team->company_id, auth()->user()), 403);
    }

    private function normalizeCompany(array $data): array
    {
        $companyId = CompanyContext::id();

        if ($companyId !== null && $companyId > 0) {
            $data['company_id'] = $companyId;
        }

        if (empty($data['company_id'])) {
            throw ValidationException::withMessages([
                'company_id' => 'Selecciona la liga deportiva del equipo.',
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

        $data['name'] = str((string) $data['name'])->squish()->toString();
        $data['name_normalized'] = Team::normalizeName($data['name']);
        $data['founded_at'] = $data['founded_at'] ?? now()->toDateString();

        return $data;
    }

    private function dataSnapshot(Team $team): array
    {
        return [
            'name' => $team->name,
            'founded_at' => $team->founded_at?->format('Y-m-d'),
            'notes' => $team->notes,
            'is_active' => (bool) $team->is_active,
        ];
    }
}
