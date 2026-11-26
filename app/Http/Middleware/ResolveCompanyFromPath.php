<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyFromPath
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = Company::query()
            ->where('subdomain', $request->route('tenant'))
            ->where('is_active', true)
            ->where('public_page_is_enabled', true)
            ->firstOrFail();

        app()->instance('tenant.company', $company);

        return $next($request);
    }
}
