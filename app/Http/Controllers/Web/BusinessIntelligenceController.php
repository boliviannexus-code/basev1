<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Reports\BusinessIntelligenceService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusinessIntelligenceController extends Controller
{
    public function __construct(
        private readonly BusinessIntelligenceService $businessIntelligence,
    ) {}

    public function index(Request $request): View
    {
        $company = CompanyContext::activeCompany($request->user());
        abort_unless($company, 403);

        $companyId = (int) $company->id;
        $filters = $this->businessIntelligence->filters($request->query());

        return view('business-intelligence.index', [
            ...$this->businessIntelligence->options($companyId),
            ...$this->businessIntelligence->build($companyId, $filters),
            'filters' => $filters,
            'company' => $company,
        ]);
    }
}
