<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TourBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourBookingAdminController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        $filters = $request->only(['status', 'q', 'date_from', 'date_to']);

        $bookings = TourBooking::query()
            ->with(['tour.company', 'tour.category', 'user'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('travel_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('travel_date', '<=', $date))
            ->when($filters['q'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('booking_code', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('tour', fn ($tourQuery) => $tourQuery->where('title', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('bookings.index', [
            'bookings' => $bookings,
            'filters' => $filters,
            'statuses' => TourBooking::STATUSES,
        ]);
    }

    public function show(Request $request, TourBooking $booking): View
    {
        abort_unless($request->user()?->can('bookings.view'), 403);

        $booking->load(['tour.company', 'tour.category', 'tour.guideType', 'availability', 'user']);

        return view('bookings.show', [
            'booking' => $booking,
            'statuses' => TourBooking::STATUSES,
        ]);
    }

    public function updateStatus(Request $request, TourBooking $booking): RedirectResponse
    {
        abort_unless($request->user()?->can('bookings.manage'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(TourBooking::STATUSES))],
        ]);

        $booking->update(['status' => $validated['status']]);

        return back()->with('success', 'Estado de reserva actualizado correctamente.');
    }
}
