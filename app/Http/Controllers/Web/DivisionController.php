<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Division\StoreDivisionRequest;
use App\Http\Requests\Division\UpdateDivisionRequest;
use App\Models\Company;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Services\DivisionService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DivisionController extends Controller
{
    public function __construct(
        private readonly DivisionService $divisions
    ) {}

    public function index(): View
    {
        return view('divisions.index', [
            'divisions' => $this->divisions->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('divisions.partials.create-form', $data);
        }

        return view('divisions.create', $data);
    }

    public function store(StoreDivisionRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $division = $this->divisions->create($request->validated());
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Division creada correctamente.',
                'data' => ['id' => $division->id],
            ], 201);
        }

        return redirect()->route('divisions.index')->with('success', 'Division creada correctamente.');
    }

    public function show(Request $request, Division $division): View
    {
        $this->divisions->ensureVisible($division);

        $division->load(['company', 'categories']);

        if ($request->ajax()) {
            return view('divisions.partials.show', compact('division'));
        }

        return view('divisions.show', compact('division'));
    }

    public function edit(Request $request, Division $division): View
    {
        $this->divisions->ensureVisible($division);
        $division->load('categories');

        $data = $this->formData(['division' => $division]);

        if ($request->ajax()) {
            return view('divisions.partials.edit-form', $data);
        }

        return view('divisions.edit', $data);
    }

    public function update(UpdateDivisionRequest $request, Division $division): JsonResponse|RedirectResponse
    {
        try {
            $division = $this->divisions->update($division, $request->validated());
        } catch (ValidationException $exception) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => collect($exception->errors())->flatten()->first(),
                    'data' => $exception->errors(),
                ], 422);
            }

            return back()->withErrors($exception->errors())->withInput();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Division actualizada correctamente.',
                'data' => ['id' => $division->id],
            ]);
        }

        return redirect()->route('divisions.index')->with('success', 'Division actualizada correctamente.');
    }

    public function destroy(Division $division): RedirectResponse
    {
        try {
            $this->divisions->delete($division);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('divisions.index')->with('success', 'Division eliminada correctamente.');
    }

    private function formData(array $data = []): array
    {
        $selectedCompanyId = $data['division']->company_id ?? CompanyContext::id();

        return $data + [
            'companies' => Company::query()
                ->when(CompanyContext::id(), fn ($query, $companyId) => $query->whereKey($companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'categories' => CompanyContext::scope(DivisionCategory::query())
                ->with('division')
                ->when($selectedCompanyId, fn ($query, $companyId) => $query->where('company_id', $companyId))
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}
