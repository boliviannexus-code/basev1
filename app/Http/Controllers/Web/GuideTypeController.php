<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\GuideType\StoreGuideTypeRequest;
use App\Http\Requests\GuideType\UpdateGuideTypeRequest;
use App\Models\GuideType;
use App\Services\GuideTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuideTypeController extends Controller
{
    public function __construct(
        private readonly GuideTypeService $guideTypes
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('guide_types.view'), 403);

        return view('guide-types.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('guide_types.create'), 403);

        if ($request->ajax()) {
            return view('guide-types.partials.create-form');
        }

        return view('guide-types.create');
    }

    public function store(StoreGuideTypeRequest $request): JsonResponse|RedirectResponse
    {
        $guideType = $this->guideTypes->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de guia creado correctamente.',
                'data' => ['id' => $guideType->id],
            ], 201);
        }

        return redirect()->route('guide-types.index')->with('success', 'Tipo de guia creado correctamente.');
    }

    public function show(Request $request, GuideType $guideType): View
    {
        abort_unless($request->user()?->can('guide_types.view'), 403);

        if ($request->ajax()) {
            return view('guide-types.partials.show', compact('guideType'));
        }

        return view('guide-types.show', compact('guideType'));
    }

    public function edit(Request $request, GuideType $guideType): View
    {
        abort_unless($request->user()?->can('guide_types.update'), 403);

        if ($request->ajax()) {
            return view('guide-types.partials.edit-form', compact('guideType'));
        }

        return view('guide-types.edit', compact('guideType'));
    }

    public function update(UpdateGuideTypeRequest $request, GuideType $guideType): JsonResponse|RedirectResponse
    {
        $guideType = $this->guideTypes->update($guideType, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de guia actualizado correctamente.',
                'data' => ['id' => $guideType->id],
            ]);
        }

        return redirect()->route('guide-types.index')->with('success', 'Tipo de guia actualizado correctamente.');
    }

    public function destroy(GuideType $guideType): RedirectResponse
    {
        abort_unless(auth()->user()?->can('guide_types.delete'), 403);

        $this->guideTypes->delete($guideType);

        return redirect()->route('guide-types.index')->with('success', 'Tipo de guia eliminado correctamente.');
    }
}
