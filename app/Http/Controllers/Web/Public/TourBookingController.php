<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tourist\StoreTourBookingRequest;
use App\Models\Tour;
use App\Services\PublicTourService;
use App\Services\TourBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TourBookingController extends Controller
{
    public function __construct(
        private readonly PublicTourService $publicTours,
        private readonly TourBookingService $bookings,
    ) {}

    public function create(Request $request, Tour $tour): View
    {
        $tour = $this->publicTours->findPublicTour($tour);
        $people = max(1, (int) $request->integer('people', 1));
        $travelDate = $request->string('date')->toString() ?: $tour->availabilities->first()?->date?->toDateString();
        $quote = null;

        if ($travelDate) {
            try {
                $quote = $this->bookings->quote($tour, $travelDate, $people);
            } catch (\Throwable) {
                $quote = null;
            }
        }

        [$firstName, $lastName] = $this->splitName((string) $request->user()->name);

        return view('public.bookings.create', [
            'tour' => $tour,
            'travelDate' => $travelDate,
            'people' => $people,
            'quote' => $quote,
            'firstName' => $firstName,
            'lastName' => $lastName,
        ]);
    }

    public function store(StoreTourBookingRequest $request, Tour $tour): RedirectResponse
    {
        $tour = $this->publicTours->findPublicTour($tour);
        $booking = $this->bookings->create($request->user(), $tour, $request->validated());

        return redirect()
            ->route('tourist.reservations.show', $booking)
            ->with('success', 'Reserva confirmada correctamente.');
    }

    private function splitName(string $name): array
    {
        $parts = Str::of($name)->squish()->explode(' ')->filter()->values();

        return [
            $parts->first() ?? '',
            $parts->slice(1)->implode(' '),
        ];
    }
}
