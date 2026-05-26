<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\TourBooking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TouristPanelController extends Controller
{
    public function reservations(Request $request): View
    {
        return view('public.account.reservations', [
            'bookings' => $request->user()
                ->tourBookings()
                ->with(['tour.images', 'tour.category'])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Request $request, TourBooking $booking): View
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        return view('public.account.booking-show', [
            'booking' => $booking->load(['tour.images', 'tour.category', 'tour.guideType', 'availability']),
        ]);
    }

    public function voucher(Request $request, TourBooking $booking): View
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403);

        return view('public.account.voucher', [
            'booking' => $booking->load(['tour.company', 'tour.category', 'availability']),
        ]);
    }

    public function history(Request $request): View
    {
        return view('public.account.history', [
            'bookings' => $request->user()
                ->tourBookings()
                ->with(['tour.images'])
                ->whereIn('status', [TourBooking::STATUS_COMPLETED, TourBooking::STATUS_CANCELLED])
                ->latest()
                ->paginate(10),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('public.account.profile', [
            'user' => $request->user(),
        ]);
    }
}
