<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\DivisionCategory\StoreDivisionCategoryRequest;
use App\Http\Requests\DivisionCategory\UpdateDivisionCategoryRequest;
use App\Models\Division;
use App\Models\DivisionCategory;
use App\Services\DivisionCategoryService;
use App\Support\CompanyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly DivisionCategoryService $categories
    ) {}

    public function index(): View
    {
        return view('categories.index', [
            'categories' => $this->categories->paginate(),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $this->formData();

        if ($request->ajax()) {
            return view('categories.partials.create-form', $data);
        }

        return view('categories.create', $data);
    }

    public function store(StoreDivisionCategoryRequest $request): JsonResponse|RedirectResponse
    {
        try {
            $category = $this->categories->create($request->validated());
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
                'message' => 'Categoria creada correctamente.',
                'data' => ['id' => $category->id],
            ], 201);
        }

        return redirect()->route('categories.index')->with('success', 'Categoria creada correctamente.');
    }

    public function show(Request $request, DivisionCategory $category): View
    {
        $this->categories->ensureVisible($category);
        $category->load(['company', 'division']);

        if ($request->ajax()) {
            return view('categories.partials.show', compact('category'));
        }

        return view('categories.show', compact('category'));
    }

    public function edit(Request $request, DivisionCategory $category): View
    {
        $this->categories->ensureVisible($category);

        $data = $this->formData(['category' => $category]);

        if ($request->ajax()) {
            return view('categories.partials.edit-form', $data);
        }

        return view('categories.edit', $data);
    }

    public function update(UpdateDivisionCategoryRequest $request, DivisionCategory $category): JsonResponse|RedirectResponse
    {
        try {
            $category = $this->categories->update($category, $request->validated());
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
                'message' => 'Categoria actualizada correctamente.',
                'data' => ['id' => $category->id],
            ]);
        }

        return redirect()->route('categories.index')->with('success', 'Categoria actualizada correctamente.');
    }

    public function destroy(DivisionCategory $category): RedirectResponse
    {
        $this->categories->delete($category);

        return redirect()->route('categories.index')->with('success', 'Categoria eliminada correctamente.');
    }

    private function formData(array $data = []): array
    {
        return $data + [
            'divisions' => CompanyContext::scope(Division::query())
                ->with('company')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        ];
    }
}
