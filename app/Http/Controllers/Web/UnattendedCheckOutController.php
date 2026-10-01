<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Stay;
use App\Services\CheckIn\UnattendedCheckOutService;
use App\Services\Reservations\ReservationGroupManagementService;
use App\Services\Reservations\ReservationManagementService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UnattendedCheckOutController extends Controller
{
    public function index(): View
    {
        $companyId = CompanyContext::id();
        $stays = Stay::query()
            ->where('company_id', $companyId)
            ->where('status', 'occupied')
            ->whereDate('check_out_date', '<', today())
            ->with(['space', 'room', 'bedUnit', 'holderGuest', 'accountStatement'])
            ->orderBy('check_out_date')->orderBy('id')
            ->paginate(25);

        $reservationGroups = ReservationGroup::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['pending_payment', 'payment_under_review', 'confirmed'])
            ->whereDate('check_in', '<', today())
            ->with(['reservationChannel', 'reservations.space', 'reservations.room', 'reservations.bedUnitItems.bedUnit.room'])
            ->orderBy('check_in')->orderBy('id')
            ->get();

        $reservations = Reservation::query()
            ->where('company_id', $companyId)
            ->whereNull('reservation_group_id')
            ->whereIn('status', ['pending_payment', 'payment_under_review', 'confirmed'])
            ->whereDate('check_in', '<', today())
            ->with(['space', 'room', 'reservationChannel', 'roomItems.room', 'bedUnitItems.bedUnit.room'])
            ->orderBy('check_in')->orderBy('id')
            ->get();

        return view('unattended-checkouts.index', compact('stays', 'reservationGroups', 'reservations'));
    }

    public function resolve(Request $request, Stay $stay, UnattendedCheckOutService $resolutions): RedirectResponse
    {
        abort_unless((int) $stay->company_id === (int) CompanyContext::id(), 404);
        $data = $request->validate([
            'action' => ['required', Rule::in(['extend', 'checkout'])],
            'expected_check_out_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required_if:action,checkout', 'nullable', 'string', 'min:3', 'max:1000'],
        ], [
            'action.required' => 'No se recibio la accion seleccionada. Vuelva a presionar el boton de resolucion.',
            'reason.required_if' => 'Ingrese un comentario para registrar el check-out tardio en el historial.',
            'reason.min' => 'El comentario debe tener al menos 3 caracteres.',
        ]);

        $resolutions->resolve($stay, $request->user(), $data);

        return back()->with('success', $data['action'] === 'extend'
            ? 'Se agrego una noche. Si la salida sigue vencida, el caso permanece sin atencion.'
            : 'Check-out tardio registrado correctamente.');
    }

    public function resolveReservation(Request $request, Reservation $reservation, ReservationManagementService $reservations): RedirectResponse
    {
        abort_unless((int) $reservation->company_id === (int) CompanyContext::id(), 404);
        $data = $this->validateArrivalResolution($request);

        if (! $reservation->isUnattendedArrival() || $reservation->check_in->toDateString() !== $data['expected_check_in']) {
            throw ValidationException::withMessages(['action' => 'La reserva ya cambio. Recargue la lista antes de resolverla.']);
        }

        $reason = $data['reason'] ?? null;
        $data['action'] === 'cancel'
            ? $reservations->cancel($reservation, $reason)
            : $reservations->noShow($reservation, $reason);

        return back()->with('success', $data['action'] === 'cancel'
            ? 'Reserva cancelada y disponibilidad liberada.'
            : 'Reserva marcada como no show y disponibilidad liberada.');
    }

    public function resolveReservationGroup(Request $request, ReservationGroup $group, ReservationGroupManagementService $groups): RedirectResponse
    {
        abort_unless((int) $group->company_id === (int) CompanyContext::id(), 404);
        $data = $this->validateArrivalResolution($request);

        if (! $group->isUnattendedArrival() || $group->check_in->toDateString() !== $data['expected_check_in']) {
            throw ValidationException::withMessages(['action' => 'La reserva ya cambio. Recargue la lista antes de resolverla.']);
        }

        $reason = $data['reason'] ?? null;
        $data['action'] === 'cancel'
            ? $groups->cancel($group, $reason)
            : $groups->noShow($group, $reason);

        return back()->with('success', $data['action'] === 'cancel'
            ? 'Reserva cancelada y disponibilidad liberada.'
            : 'Reserva marcada como no show y disponibilidad liberada.');
    }

    private function validateArrivalResolution(Request $request): array
    {
        return $request->validate([
            'action' => ['required', Rule::in(['cancel', 'no_show'])],
            'expected_check_in' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'action.required' => 'No se recibio la accion seleccionada. Vuelva a presionar el boton de resolucion.',
            'reason.required' => 'Ingrese un comentario para registrar esta decision en el historial.',
            'reason.min' => 'El comentario debe tener al menos 3 caracteres.',
        ]);
    }
}
