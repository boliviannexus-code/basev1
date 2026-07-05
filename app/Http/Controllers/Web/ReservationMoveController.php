<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\MoveReservationRequest;
use App\Models\Reservation;
use App\Services\Reservations\ReservationMoveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReservationMoveController extends Controller
{
    public function __construct(
        private readonly ReservationMoveService $moves,
    ) {}

    public function create(Request $request, Reservation $reservation): View
    {
        $this->ensureOwnership($reservation);
        $reservation->loadMissing([
            'space',
            'room',
            'roomItems.room',
            'bedUnitItems.bedUnit.room',
            'reservationGroup.accountStatement',
        ]);

        $checkIn = CarbonImmutable::parse($request->query('check_in', $reservation->check_in->toDateString()));
        $checkOut = CarbonImmutable::parse($request->query('check_out', $reservation->check_out->toDateString()));

        if ($checkIn->lt(today())) {
            $checkIn = CarbonImmutable::today();
        }

        if ($checkOut->lte($checkIn)) {
            $checkOut = $checkIn->addDay();
        }

        return view('reservations.move.create', [
            'reservation' => $reservation,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'resources' => $this->moves->availableResources($reservation, $checkIn->toDateString(), $checkOut->toDateString()),
        ]);
    }

    public function store(MoveReservationRequest $request, Reservation $reservation): JsonResponse|RedirectResponse
    {
        $this->ensureOwnership($reservation);
        $this->moves->move($reservation, $request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Reserva movida correctamente.',
                'refresh_occupancy' => true,
            ]);
        }

        return redirect()
            ->route('admin.reservation-groups.show', $reservation->reservation_group_id)
            ->with('success', 'Reserva movida correctamente.');
    }

    private function ensureOwnership(Reservation $reservation): void
    {
        abort_unless((int) $reservation->company_id === (int) auth()->user()?->company_id, 404);
    }
}
