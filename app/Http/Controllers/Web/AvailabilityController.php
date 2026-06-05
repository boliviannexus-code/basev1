<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Availability\BulkUpdateAvailabilityRequest;
use App\Http\Requests\Availability\StoreAvailabilityDayRequest;
use App\Services\Availability\AvailabilityGridService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityGridService $grid,
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
        Gate::authorize('availability.view');

        return response()->json($this->grid->gridData($this->companyId(), $request->only([
            'start_date',
            'type',
            'space_id',
            'status',
        ])));
    }

    public function storeDay(StoreAvailabilityDayRequest $request): JsonResponse
    {
        $day = $this->grid->saveDay($this->companyId(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Disponibilidad guardada correctamente.',
            'data' => ['id' => $day->id],
        ]);
    }

    public function bulkUpdate(BulkUpdateAvailabilityRequest $request): JsonResponse
    {
        $count = $this->grid->bulkUpdate($this->companyId(), $request->validated());

        return response()->json([
            'success' => true,
            'message' => "Disponibilidad actualizada en {$count} celdas.",
            'data' => ['count' => $count],
        ]);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
