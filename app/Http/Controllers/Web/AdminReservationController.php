<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\Reservations\ReservationManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminReservationController extends Controller
{
    public function __construct(
        private readonly ReservationManagementService $reservationManagement,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('reservations.view');

        $companyId = $this->companyId();
        $this->reservationManagement->expireOverduePending($companyId);
        $status = $request->query('status');

        $reservations = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['space', 'room', 'rooms', 'user', 'reservationChannel'])
            ->where('company_id', $companyId)
            ->when(filled($status), fn (Builder $query): Builder => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = Reservation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('reservations.admin.index', [
            'reservations' => $reservations,
            'counts' => $counts,
            'status' => $status,
        ]);
    }

    public function show(int $reservation): View
    {
        Gate::authorize('reservations.view');

        $reservation = $this->reservation($reservation);

        return view('reservations.admin.show', ['reservation' => $reservation]);
    }

    public function approve(Request $request, int $reservation): RedirectResponse
    {
        Gate::authorize('reservations.manage');

        $reservation = $this->reservation($reservation);
        abort_unless(in_array($reservation->status, ['pending_payment', 'payment_under_review'], true), 403);

        $this->reservationManagement->approve($reservation, (int) $request->user()->id);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Reserva aprobada y disponibilidad bloqueada definitivamente.');
    }

    public function reject(Request $request, int $reservation): RedirectResponse
    {
        Gate::authorize('reservations.manage');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation = $this->reservation($reservation);
        abort_unless(in_array($reservation->status, ['pending_payment', 'payment_under_review'], true), 403);

        $this->reservationManagement->reject($reservation, $data['reason'] ?? null);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Reserva rechazada y disponibilidad liberada.');
    }

    public function cancel(Request $request, int $reservation): RedirectResponse
    {
        Gate::authorize('reservations.manage');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation = $this->reservation($reservation);
        abort_unless(! in_array($reservation->status, ['cancelled', 'rejected', 'expired'], true), 403);

        $this->reservationManagement->cancel($reservation, $data['reason'] ?? null);

        return redirect()
            ->route('admin.reservations.show', $reservation)
            ->with('success', 'Reserva cancelada y disponibilidad liberada.');
    }

    private function reservation(int $reservation): Reservation
    {
        return Reservation::query()
            ->withoutGlobalScope('company')
            ->with([
                'company',
                'space.location',
                'room',
                'rooms',
                'roomItems.room',
                'extraCharges.category',
                'roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservationChannel',
                'user',
                'occupancyBlock' => fn ($query) => $query->withTrashed(),
            ])
            ->where('company_id', $this->companyId())
            ->whereKey($reservation)
            ->firstOrFail();
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
