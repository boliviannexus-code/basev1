<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Occupancy\StoreOccupancyBlockRequest;
use App\Http\Requests\Occupancy\UpdateOccupancyBlockRequest;
use App\Models\OccupancyBlock;
use App\Services\Occupancy\OccupancyGridService;
use App\Services\Occupancy\OccupancyValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OccupancyController extends Controller
{
    public function __construct(
        private readonly OccupancyGridService $grid,
        private readonly OccupancyValidationService $validation,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('occupancy.view');

        $filters = $request->only([
            'week_start',
            'type',
            'space_id',
            'status',
        ]);

        return view('occupancy.index', [
            'spaces' => $this->grid->spacesForFilters($this->companyId()),
            'initialWeek' => $this->grid->weekData($this->companyId(), $filters),
            'filters' => $filters,
        ]);
    }

    public function weekData(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.view');

        return response()->json($this->grid->weekData($this->companyId(), $request->only([
            'week_start',
            'type',
            'space_id',
            'status',
        ])));
    }

    public function storeBlock(StoreOccupancyBlockRequest $request): JsonResponse
    {
        $companyId = $this->companyId();
        $data = $request->validated();
        [$space, $room, $bedUnit] = $this->validation->validatePayload($data, $companyId);

        $block = OccupancyBlock::query()->create([
            ...$data,
            'company_id' => $companyId,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo creado correctamente.',
            'data' => ['id' => $block->id],
        ], 201);
    }

    public function updateBlock(UpdateOccupancyBlockRequest $request, OccupancyBlock $occupancyBlock): JsonResponse
    {
        $this->ensureOwnership($occupancyBlock);

        $companyId = $this->companyId();
        $data = $request->validated();
        [$space, $room, $bedUnit] = $this->validation->validatePayload($data, $companyId, $occupancyBlock);

        $occupancyBlock->update([
            ...$data,
            'company_id' => $companyId,
            'space_id' => $space->id,
            'space_room_id' => $room?->id,
            'room_bed_unit_id' => $bedUnit?->id,
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo actualizado correctamente.',
            'data' => ['id' => $occupancyBlock->id],
        ]);
    }

    public function destroyBlock(OccupancyBlock $occupancyBlock): JsonResponse
    {
        Gate::authorize('occupancy.manage');
        $this->ensureOwnership($occupancyBlock);

        $occupancyBlock->update(['status' => 'cancelled']);
        $occupancyBlock->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bloqueo eliminado correctamente.',
        ]);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }

    private function ensureOwnership(OccupancyBlock $occupancyBlock): void
    {
        abort_unless((int) $occupancyBlock->company_id === $this->companyId(), 403);
    }
}
