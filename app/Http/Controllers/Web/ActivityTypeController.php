<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ActivityType\StoreActivityTypeRequest;
use App\Http\Requests\ActivityType\UpdateActivityTypeRequest;
use App\Models\ActivityType;
use App\Services\ActivityTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ActivityTypeController extends Controller
{
    public function __construct(
        private readonly ActivityTypeService $activityTypes
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('activity_types.view'), 403);

        return view('activity-types.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('activity_types.create'), 403);

        if ($request->ajax()) {
            return view('activity-types.partials.create-form');
        }

        return view('activity-types.create');
    }

    public function store(StoreActivityTypeRequest $request): JsonResponse|RedirectResponse
    {
        $activityType = $this->activityTypes->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de actividad creado correctamente.',
                'data' => ['id' => $activityType->id],
            ], 201);
        }

        return redirect()->route('activity-types.index')->with('success', 'Tipo de actividad creado correctamente.');
    }

    public function show(Request $request, ActivityType $activityType): View
    {
        abort_unless($request->user()?->can('activity_types.view'), 403);

        if ($request->ajax()) {
            return view('activity-types.partials.show', compact('activityType'));
        }

        return view('activity-types.show', compact('activityType'));
    }

    public function edit(Request $request, ActivityType $activityType): View
    {
        abort_unless($request->user()?->can('activity_types.update'), 403);

        if ($request->ajax()) {
            return view('activity-types.partials.edit-form', compact('activityType'));
        }

        return view('activity-types.edit', compact('activityType'));
    }

    public function update(UpdateActivityTypeRequest $request, ActivityType $activityType): JsonResponse|RedirectResponse
    {
        $activityType = $this->activityTypes->update($activityType, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de actividad actualizado correctamente.',
                'data' => ['id' => $activityType->id],
            ]);
        }

        return redirect()->route('activity-types.index')->with('success', 'Tipo de actividad actualizado correctamente.');
    }

    public function destroy(ActivityType $activityType): RedirectResponse
    {
        abort_unless(auth()->user()?->can('activity_types.delete'), 403);

        $this->activityTypes->delete($activityType);

        return redirect()->route('activity-types.index')->with('success', 'Tipo de actividad eliminado correctamente.');
    }
}
