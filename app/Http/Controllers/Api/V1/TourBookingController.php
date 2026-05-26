<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tourist\StoreTourBookingRequest;
use App\Http\Resources\TourBookingResource;
use App\Models\Tour;
use App\Models\TourBooking;
use App\Services\PublicTourService;
use App\Services\TourBookingService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TourBookingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PublicTourService $publicTours,
        private readonly TourBookingService $bookings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $bookings = $request->user()
            ->tourBookings()
            ->with(['tour.images', 'tour.category', 'availability'])
            ->latest()
            ->paginate(10);

        return $this->successResponse([
            'items' => TourBookingResource::collection($bookings->items()),
            'meta' => [
                'current_page' => $bookings->currentPage(),
                'last_page' => $bookings->lastPage(),
                'per_page' => $bookings->perPage(),
                'total' => $bookings->total(),
            ],
        ]);
    }

    public function store(StoreTourBookingRequest $request, Tour $tour): JsonResponse
    {
        $tour = $this->publicTours->findPublicTour($tour);
        $booking = $this->bookings->create($request->user(), $tour, $request->validated());

        return $this->successResponse(new TourBookingResource($booking), 'Reserva confirmada correctamente.', 201);
    }

    public function show(Request $request, TourBooking $booking): JsonResponse
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        return $this->successResponse(new TourBookingResource(
            $booking->load(['tour.images', 'tour.category', 'tour.guideType', 'availability'])
        ));
    }
}
