<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransportType\StoreTransportTypeRequest;
use App\Http\Requests\TransportType\UpdateTransportTypeRequest;
use App\Models\TransportType;
use App\Services\TransportTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransportTypeController extends Controller
{
    public function __construct(
        private readonly TransportTypeService $transportTypes
    ) {}

    public function index(): View
    {
        abort_unless(auth()->user()?->can('transport_types.view'), 403);

        return view('transport-types.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('transport_types.create'), 403);

        if ($request->ajax()) {
            return view('transport-types.partials.create-form');
        }

        return view('transport-types.create');
    }

    public function store(StoreTransportTypeRequest $request): JsonResponse|RedirectResponse
    {
        $transportType = $this->transportTypes->create($request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de transporte creado correctamente.',
                'data' => ['id' => $transportType->id],
            ], 201);
        }

        return redirect()->route('transport-types.index')->with('success', 'Tipo de transporte creado correctamente.');
    }

    public function show(Request $request, TransportType $transportType): View
    {
        abort_unless($request->user()?->can('transport_types.view'), 403);

        if ($request->ajax()) {
            return view('transport-types.partials.show', compact('transportType'));
        }

        return view('transport-types.show', compact('transportType'));
    }

    public function edit(Request $request, TransportType $transportType): View
    {
        abort_unless($request->user()?->can('transport_types.update'), 403);

        if ($request->ajax()) {
            return view('transport-types.partials.edit-form', compact('transportType'));
        }

        return view('transport-types.edit', compact('transportType'));
    }

    public function update(UpdateTransportTypeRequest $request, TransportType $transportType): JsonResponse|RedirectResponse
    {
        $transportType = $this->transportTypes->update($transportType, $request->validated());

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Tipo de transporte actualizado correctamente.',
                'data' => ['id' => $transportType->id],
            ]);
        }

        return redirect()->route('transport-types.index')->with('success', 'Tipo de transporte actualizado correctamente.');
    }

    public function destroy(TransportType $transportType): RedirectResponse
    {
        abort_unless(auth()->user()?->can('transport_types.delete'), 403);

        $this->transportTypes->delete($transportType);

        return redirect()->route('transport-types.index')->with('success', 'Tipo de transporte eliminado correctamente.');
    }
}
