<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCompanyFromSubdomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $this->subdomainFromHost($request->getHost());

        if ($subdomain) {
            $company = Company::query()
                ->where('subdomain', $subdomain)
                ->where('is_active', true)
                ->first();

            abort_unless($company, 404);

            app()->instance('tenant.company', $company);
        }

        return $next($request);
    }

    private function subdomainFromHost(string $host): ?string
    {
        $host = strtolower(preg_replace('/:\d+$/', '', $host) ?? $host);
        $baseDomain = strtolower((string) config('tenancy.base_domain'));

        if ($baseDomain === '' || $host === $baseDomain || ! str_ends_with($host, '.'.$baseDomain)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen('.'.$baseDomain));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        if (in_array($subdomain, config('tenancy.central_subdomains', []), true)) {
            return null;
        }

        return Company::normalizeSubdomain($subdomain);
    }
}
