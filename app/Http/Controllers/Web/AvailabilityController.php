<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\BulkUpdateAvailabilityRequest;
use App\Http\Requests\Availability\StoreAvailabilityStatusRequest;
use App\Http\Requests\Availability\UpdateAvailabilityStatusRequest;
use App\Models\AvailabilityStatus;
use App\Services\Availability\AvailabilityGridService;
use App\Services\Availability\AvailabilityStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityGridService $grid,
        private readonly AvailabilityStatusService $statuses,
    ) {}

    public function index(): View
    {
        Gate::authorize('availability.view');

        return view('availability.index', [
            'spaces' => $this->grid->spacesForFilters($this->companyId()),
            'initialGrid' => $this->grid->gridData($this->companyId(), []),
        ]);
    }

    public function gridData(Request $request): JsonResponse
    {
        return $this->weekData($request);
    }

    public function weekData(Request $request): JsonResponse
    {
        Gate::authorize('availability.view');

        return response()->json($this->grid->gridData($this->companyId(), $request->only([
            'week_start',
            'type',
            'space_id',
            'status',
            'start_date',
        ])));
    }

    public function storeDay(StoreAvailabilityStatusRequest $request): JsonResponse
    {
        return $this->storeStatus($request);
    }

    public function storeStatus(StoreAvailabilityStatusRequest $request): JsonResponse
    {
        $status = $this->statuses->changeStatus($this->companyId(), $request->validated(), null, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Estado de disponibilidad guardado correctamente.',
            'data' => ['id' => $status?->id],
        ]);
    }

    public function updateStatus(UpdateAvailabilityStatusRequest $request, AvailabilityStatus $availabilityStatus): JsonResponse
    {
        $status = $this->statuses->changeStatus($this->companyId(), $request->validated(), $availabilityStatus, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Estado de disponibilidad actualizado correctamente.',
            'data' => ['id' => $status?->id],
        ]);
    }

    public function bulkUpdate(BulkUpdateAvailabilityRequest $request): JsonResponse
    {
        $result = $this->statuses->bulkChange($this->companyId(), $request->validated(), $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Cambios aplicados en bloque correctamente.',
            'data' => $result,
        ]);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
