<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

class CompanyOnlineStatusController extends Controller
{
    public function enable(Company $company): RedirectResponse
    {
        $company->forceFill([
            'is_online_enabled_by_admin' => true,
        ])->save();

        return back()->with('success', 'Empresa habilitada para página pública online.');
    }

    public function disable(Company $company): RedirectResponse
    {
        $company->forceFill([
            'is_online_enabled_by_admin' => false,
        ])->save();

        return back()->with('success', 'Empresa deshabilitada para página pública online.');
    }
}
