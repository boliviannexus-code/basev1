<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Category\StoreCategoryRequest;
use App\Http\Requests\Category\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('categories.view'), 403);

        return view('categories.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('categories.create'), 403);

        if ($request->ajax()) {
            return view('categories.partials.create-form');
        }

        return view('categories.create');
    }

    public function store(StoreCategoryRequest $request): JsonResponse|RedirectResponse
    {
        $category = $this->categories->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria creada correctamente.',
                'data' => ['id' => $category->id],
            ], 201);
        }

        return redirect()->route('categories.index')->with('success', 'Categoria creada correctamente.');
    }

    public function show(Request $request, Category $category): View
    {
        abort_unless($request->user()?->can('categories.view'), 403);

        if ($request->ajax()) {
            return view('categories.partials.show', compact('category'));
        }

        return view('categories.show', compact('category'));
    }

    public function edit(Request $request, Category $category): View
    {
        abort_unless($request->user()?->can('categories.update'), 403);

        if ($request->ajax()) {
            return view('categories.partials.edit-form', compact('category'));
        }

        return view('categories.edit', compact('category'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse|RedirectResponse
    {
        $category = $this->categories->update($category, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Categoria actualizada correctamente.',
                'data' => ['id' => $category->id],
            ]);
        }

        return redirect()->route('categories.index')->with('success', 'Categoria actualizada correctamente.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        abort_unless(auth()->user()?->can('categories.delete'), 403);

        $this->categories->delete($category);

        return redirect()->route('categories.index')->with('success', 'Categoria eliminada correctamente.');
    }
}
