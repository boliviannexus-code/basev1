<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExchangeRate;
use App\Models\Reservation;
use App\Models\ReservationGroup;
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
            ->whereNull('reservation_group_id')
            ->when(filled($status), fn (Builder $query): Builder => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $reservationGroups = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->with(['reservationChannel', 'reservations.space'])
            ->where('company_id', $companyId)
            ->when(filled($status), fn (Builder $query): Builder => $query->where('status', $status))
            ->latest()
            ->get();

        $counts = Reservation::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->whereNull('reservation_group_id')
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $groupCounts = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->where('company_id', $companyId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $groupCounts->each(function ($total, string $status) use ($counts): void {
            $counts[$status] = (int) ($counts[$status] ?? 0) + (int) $total;
        });

        return view('reservations.admin.index', [
            'reservations' => $reservations,
            'reservationGroups' => $reservationGroups,
            'counts' => $counts,
            'status' => $status,
            'currentExchangeRate' => ExchangeRate::currentForCompany($companyId),
        ]);
    }

    public function show(Request $request, int $reservation): View|RedirectResponse
    {
        Gate::authorize('reservations.view');

        $reservation = $this->reservation($reservation);

        if ($reservation->reservation_group_id) {
            return redirect()->route('admin.reservation-groups.show', $reservation->reservation_group_id);
        }

        return view($request->ajax() ? 'reservations.admin.partials.show-content' : 'reservations.admin.show', [
            'reservation' => $reservation,
            'occupancyUrl' => $this->occupancyUrlForReservation($reservation),
            'currentExchangeRate' => ExchangeRate::currentForCompany($this->companyId()),
        ]);
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
        abort_unless(! in_array($reservation->status, ['cancelled', 'rejected', 'expired', 'no_show'], true), 403);

        $this->reservationManagement->cancel($reservation, $data['reason'] ?? null);

        return redirect()
            ->to($this->occupancyUrlForReservation($reservation))
            ->with('success', 'Reserva cancelada y disponibilidad liberada.');
    }

    public function noShow(Request $request, int $reservation): RedirectResponse
    {
        Gate::authorize('reservations.manage');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reservation = $this->reservation($reservation);
        abort_unless(! in_array($reservation->status, ['cancelled', 'rejected', 'expired', 'no_show'], true), 403);

        $this->reservationManagement->noShow($reservation, $data['reason'] ?? null);

        return redirect()
            ->to($this->occupancyUrlForReservation($reservation))
            ->with('success', 'Reserva marcada como no show y disponibilidad liberada.');
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
                'bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservationChannel',
                'reservationGroup',
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

    private function occupancyUrlForReservation(Reservation $reservation): string
    {
        $reservation->loadMissing('space.spaceMode');
        $params = [
            'week_start' => $reservation->check_in?->toDateString() ?? today()->toDateString(),
        ];

        $params['view'] = $reservation->space?->spaceMode?->slug === 'compartido'
            ? 'shared:'.$reservation->space_id
            : 'private';

        return route('occupancy.index', $params);
    }
}
