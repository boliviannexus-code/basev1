<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ExtraChargeCategory;
use App\Support\CountryCatalog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class CompanyService
{
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return Company::query()
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

        $company = Company::query()->create($data);

        CountryCatalog::seedForCompany((int) $company->id);
        ExtraChargeCategory::ensureDefaultsForCompany((int) $company->id);

        return $company;
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

        unset($data['logo'], $data['remove_logo']);

        $company->update($data);

        return $company->refresh();
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
        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        } elseif ($defaultActive !== null) {
            $data['is_active'] = $defaultActive;
        }

        return $data;
    }
}
