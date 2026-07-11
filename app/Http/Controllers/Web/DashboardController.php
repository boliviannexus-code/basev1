<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('dashboard.view');

        $company = CompanyContext::activeCompany();
        $companyId = CompanyContext::id();

        return view('dashboard.index', [
            'dashboardCompany' => $company,
            'totalUsers' => User::query()
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->count(),
            'activeUsers' => User::query()
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->count(),
            'totalRoles' => Role::query()->count(),
            'totalPermissions' => Permission::query()->count(),
            'totalCompanies' => CompanyContext::scope(Company::query(), column: 'id')->count(),
            'recentAudits' => Audit::query()
                ->with('user')
                ->when($companyId, fn ($query, int $companyId) => $query->where('company_id', $companyId))
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }
}
