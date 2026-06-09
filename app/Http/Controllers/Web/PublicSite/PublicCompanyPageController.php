<?php

namespace App\Http\Controllers\Web\PublicSite;

use App\Http\Controllers\Controller;
use App\Services\PublicSite\CompanyPublicPageService;
use Illuminate\Contracts\View\View;

class PublicCompanyPageController extends Controller
{
    public function __construct(
        private readonly CompanyPublicPageService $pages,
    ) {}

    public function show(string $company_slug): View
    {
        return view('public.companies.show', $this->pages->page($company_slug));
    }

    public function packageDetail(string $company_slug, string $package_slug): View
    {
        return view('public.companies.packages.show', $this->pages->packagePage($company_slug, $package_slug));
    }
}
