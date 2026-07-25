<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Player;
use App\Support\CompanyContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function __construct(private readonly UserSessionSecurityService $sessionSecurity) {}

    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return CompanyContext::scope(Company::query(), column: 'id')
            ->withCount('users')
            ->latest()
            ->paginate($perPage);
    }

    public function create(array $data): Company
    {
        $data = $this->normalize($data, true);

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $data['logo_path'] = $data['logo']->store('companies/logos', 'public');
        }

        unset($data['logo'], $data['remove_logo']);
        unset($data['legal_name'], $data['tax_id']);

        return Company::query()->create($data);
    }

    public function update(Company $company, array $data): Company
    {
        $data = $this->normalize($data);

        if (! empty($data['remove_logo']) && $company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
            $data['logo_path'] = null;
        }

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            if ($company->logo_path) {
                Storage::disk('public')->delete($company->logo_path);
            }

            $data['logo_path'] = $data['logo']->store('companies/logos', 'public');
        }

        unset($data['logo'], $data['remove_logo'], $data['legal_name'], $data['tax_id']);

        $oldCode = $company->code;
        $wasActive = $company->is_active;

        $company->update($data);
        $company->refresh();

        if ($wasActive && ! $company->is_active) {
            $this->sessionSecurity->revokeForCompany($company);
        }

        if ($oldCode !== $company->code) {
            $this->refreshPlayerCodes($company);
        }

        return $company;
    }

    public function delete(Company $company): bool
    {
        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        return (bool) $company->delete();
    }

    private function normalize(array $data, ?bool $defaultActive = null): array
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = Company::normalizeCode((string) $data['code']);
        }

        if (array_key_exists('subdomain', $data)) {
            $data['subdomain'] = Company::normalizeSubdomain((string) $data['subdomain']);
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($defaultActive !== null) {
            $data['is_active'] = $defaultActive;
        }

        return $data;
    }

    private function refreshPlayerCodes(Company $company): void
    {
        Player::query()
            ->whereHas('teamPlayers', fn ($query) => $query->where('company_id', $company->id))
            ->orderBy('id')
            ->select(['id'])
            ->chunkById(500, function ($players): void {
                foreach ($players as $player) {
                    $player->forceFill([
                        'internal_code' => Player::internalCodeFor($player),
                    ])->saveQuietly();
                }
            });
    }
}
