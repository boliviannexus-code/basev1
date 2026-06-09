<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateCompanyPublicProfileRequest;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CompanyPublicProfileController extends Controller
{
    public function edit(): View
    {
        $company = $this->company();

        return view('company-public-profile.edit', [
            'company' => $company,
            'spaces' => $company->spaces()
                ->with('location')
                ->orderByRaw('coalesce(title, name) asc')
                ->get(),
            'publicUrl' => $company->public_slug ? url('/p/'.$company->public_slug) : null,
        ]);
    }

    public function update(UpdateCompanyPublicProfileRequest $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validated();

        if (($data['logo'] ?? null) instanceof UploadedFile) {
            $this->replacePublicImage($company, 'logo', $data['logo'], 'companies/public/logos');
        }

        if (($data['cover_image'] ?? null) instanceof UploadedFile) {
            $this->replacePublicImage($company, 'cover_image', $data['cover_image'], 'companies/public/covers');
        }

        unset($data['logo'], $data['cover_image']);

        $company->update($data);

        return redirect()
            ->route('company.public-profile.edit')
            ->with('success', 'Perfil público actualizado correctamente.');
    }

    private function company(): Company
    {
        $company = CompanyContext::activeCompany();

        abort_unless($company instanceof Company, 403);

        return $company;
    }

    private function replacePublicImage(Company $company, string $column, UploadedFile $image, string $directory): void
    {
        $currentPath = $company->getAttribute($column);

        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        $company->forceFill([
            $column => $image->store($directory, 'public'),
        ])->save();
    }
}
