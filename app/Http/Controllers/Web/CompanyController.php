<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\CompanyService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyService $companies
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('companies.view'), 403);

        return view('companies.index', [
            'companies' => $this->companies->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('companies.create'), 403);
        abort_unless(CompanyContext::isGlobalAdmin($request->user()), 403);

        if ($request->ajax()) {
            return view('companies.partials.create-form');
        }

        return view('companies.create');
    }

    public function store(StoreCompanyRequest $request): JsonResponse|RedirectResponse
    {
        $company = $this->companies->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Liga deportiva creada correctamente.',
                'data' => ['id' => $company->id],
            ], 201);
        }

        return redirect()->route('companies.index')->with('success', 'Liga deportiva creada correctamente.');
    }

    public function show(Request $request, Company $company): View
    {
        abort_unless($request->user()?->can('companies.view'), 403);
        abort_unless(CompanyContext::belongsToUser($company->id, $request->user()), 403);

        $company->loadCount('users');

        if ($request->ajax()) {
            return view('companies.partials.show', compact('company'));
        }

        return view('companies.show', compact('company'));
    }

    public function edit(Request $request, Company $company): View
    {
        abort_unless($request->user()?->can('companies.update'), 403);
        abort_unless(CompanyContext::belongsToUser($company->id, $request->user()), 403);

        if ($request->ajax()) {
            return view('companies.partials.edit-form', compact('company'));
        }

        return view('companies.edit', compact('company'));
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse|RedirectResponse
    {
        $company = $this->companies->update($company, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Liga deportiva actualizada correctamente.',
                'data' => ['id' => $company->id],
            ]);
        }

        return redirect()->route('companies.index')->with('success', 'Liga deportiva actualizada correctamente.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        abort_unless(auth()->user()?->can('companies.delete'), 403);
        abort_unless(CompanyContext::isGlobalAdmin(auth()->user()), 403);

        $this->companies->delete($company);

        return redirect()->route('companies.index')->with('success', 'Liga deportiva eliminada correctamente.');
    }
}
