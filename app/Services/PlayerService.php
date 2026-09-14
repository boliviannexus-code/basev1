<?php

namespace App\Services;

use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class PlayerService
{
    public function paginate(?string $query = null, int $perPage = 15): LengthAwarePaginator
    {
        $query = trim((string) $query);

        return Player::query()
            ->forCompany(CompanyContext::id())
            ->with(['teamPlayers' => fn ($builder) => $builder
                ->where('company_id', CompanyContext::id())
                ->where('status', 'active')
                ->whereNull('deleted_at')
                ->with('team')])
            ->when($query !== '', fn ($builder) => $this->applySearch($builder, $query))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Player
    {
        $data = $this->normalize($data, true);
        unset($data['internal_code']);
        unset($data['company_id']);

        $player = Player::query()->create($data);

        return $this->ensureInternalCode($player);
    }

    public function findByCi(?string $ci): ?Player
    {
        $normalizedCi = Player::normalizeCi((string) $ci);

        if ($normalizedCi === '') {
            return null;
        }

        return Player::query()
            ->where('ci_normalized', $normalizedCi)
            ->first();
    }

    public function createOrReuse(array $data): Player
    {
        $existing = $this->findByCi($data['ci'] ?? null);

        if ($existing) {
            return $this->ensureInternalCode($existing);
        }

        return $this->create($data);
    }

    public function update(Player $player, array $data): Player
    {
        unset($data['internal_code']);

        $player->update($this->normalize($data));

        return $this->ensureInternalCode($player->refresh());
    }

    public function delete(Player $player): bool
    {
        return (bool) $player->delete();
    }

    public function search(?string $query, int $limit = 12): Collection
    {
        $query = trim((string) $query);

        if (mb_strlen($query) < 2) {
            return new Collection;
        }

        return Player::query()
            ->forCompany(CompanyContext::id())
            ->where('is_active', true)
            ->where(fn ($builder) => $this->applySearch($builder, $query))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();
    }

    private function applySearch($builder, string $query): void
    {
        $like = '%'.str($query)->lower()->toString().'%';
        $normalizedCi = '%'.Player::normalizeCi($query).'%';

        $builder->where(function ($search) use ($like, $normalizedCi): void {
            $search
                ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(maternal_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(internal_code) LIKE ?', [$like])
                ->orWhere('ci_normalized', 'like', $normalizedCi);
        });
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

    private function ensureInternalCode(Player $player): Player
    {
        if (blank($player->internal_code)) {
            $player->forceFill([
                'internal_code' => Player::internalCodeFor($player),
            ])->saveQuietly();
        }

        return $player->refresh();
    }
}
