<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TourAvailability;
use App\Services\TourAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class TourAvailabilityController extends Controller
{
    public function __construct(
        private readonly TourAvailabilityService $availability,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('tours.availability'), 403);

        return view('tours.availability.index', [
            'tours' => $this->availability->toursForSelect(),
            'statuses' => TourAvailability::STATUSES,
        ]);
    }

    public function grid(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('tours.availability'), 403);

        $validated = $request->validate([
            'tour_id' => ['nullable', 'integer'],
            'start_date' => ['nullable', 'date'],
            'days' => ['nullable', 'integer', 'min:7', 'max:180'],
        ]);

        return response()->json($this->availability->grid($validated));
    }

    public function updateDay(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('tours.availability'), 403);

        $validated = $request->validate($this->dayRules());
        $availability = $this->availability->saveDay($validated);

        return response()->json([
            'message' => 'Disponibilidad actualizada.',
            'data' => $availability,
        ]);
    }

    public function bulkUpdate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('tours.availability'), 403);

        $validated = $request->validate([
            'tour_id' => ['nullable', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'min:0', 'max:6'],
            'status' => ['nullable', Rule::in(array_keys(TourAvailability::STATUSES))],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'restrictions' => ['nullable', 'string', 'max:1000'],
            'bulk_price_usd' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'prices' => ['nullable', 'array'],
            'prices.*.tour_price_id' => ['nullable', 'integer'],
            'prices.*.title' => ['nullable', 'string', 'max:120'],
            'prices.*.min_people' => ['required_with:prices', 'integer', 'min:1', 'max:9999'],
            'prices.*.max_people' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'prices.*.price_usd' => ['required_with:prices', 'numeric', 'min:0', 'max:999999.99'],
        ]);

        $updated = $this->availability->bulkUpdate($validated);

        return response()->json([
            'message' => $updated.' celdas actualizadas.',
            'updated' => $updated,
        ]);
    }

    private function dayRules(): array
    {
        return [
            'tour_id' => ['required', 'integer'],
            'date' => ['required', 'date'],
            'status' => ['required', Rule::in(array_keys(TourAvailability::STATUSES))],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'restrictions' => ['nullable', 'string', 'max:1000'],
            'prices' => ['nullable', 'array'],
            'prices.*.tour_price_id' => ['nullable', 'integer'],
            'prices.*.title' => ['nullable', 'string', 'max:120'],
            'prices.*.min_people' => ['required_with:prices', 'integer', 'min:1', 'max:9999'],
            'prices.*.max_people' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'prices.*.price_usd' => ['required_with:prices', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
