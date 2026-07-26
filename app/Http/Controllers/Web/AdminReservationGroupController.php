<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reservations\UpdateReservationGroupRequest;
use App\Models\ExchangeRate;
use App\Models\ReservationChannel;
use App\Models\ReservationGroup;
use App\Services\Reservations\ReservationGroupManagementService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AdminReservationGroupController extends Controller
{
    public function __construct(
        private readonly ReservationGroupManagementService $groups,
    ) {}

    public function show(ReservationGroup $group): View
    {
        abort_unless(auth()->user()?->can('reservations.view') || auth()->user()?->can('occupancy.manage'), 403);
        $this->ensureOwnership($group);

        return view('reservations.admin.group-show', [
            'group' => $group->load([
                'reservationChannel',
                'reservations.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservations.space.spaceMode',
                'reservations.room',
                'reservations.rooms',
                'reservations.roomItems.room',
                'reservations.roomItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'reservations.bedUnitItems.bedUnit.room',
                'reservations.bedUnitItems.occupancyBlock' => fn ($query) => $query->withTrashed(),
                'accountStatement.items.extraChargeCategory',
            ]),
            'reservationChannels' => ReservationChannel::query()
                ->where('company_id', $group->company_id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'currentExchangeRate' => ExchangeRate::currentForCompany($group->company_id),
            'occupancyUrl' => $this->occupancyUrlForGroup($group),
        ]);
    }

    public function update(UpdateReservationGroupRequest $request, ReservationGroup $group): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'), 403);
        $this->ensureOwnership($group);

        $this->groups->update($group, $request->validated());

        return redirect()
            ->route('admin.reservation-groups.show', $group)
            ->with('success', 'Reserva actualizada correctamente.');
    }

    public function confirm(Request $request, ReservationGroup $group): RedirectResponse
    {
        Gate::authorize('reservations.manage');
        $this->ensureOwnership($group);

        $this->groups->confirm($group, (int) $request->user()->id);

        return redirect()
            ->route('admin.reservation-groups.show', $group)
            ->with('success', 'Reserva confirmada correctamente.');
    }

    public function cancel(Request $request, ReservationGroup $group): RedirectResponse
    {
        Gate::authorize('reservations.manage');
        $this->ensureOwnership($group);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->groups->cancel($group, $data['reason'] ?? null);

        return redirect()
            ->to($this->occupancyUrlForGroup($group))
            ->with('success', 'Reserva cancelada y disponibilidad liberada.');
    }

    public function noShow(Request $request, ReservationGroup $group): RedirectResponse
    {
        Gate::authorize('reservations.manage');
        $this->ensureOwnership($group);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->groups->noShow($group, $data['reason'] ?? null);

        return redirect()
            ->to($this->occupancyUrlForGroup($group))
            ->with('success', 'Reserva marcada como no show y disponibilidad liberada.');
    }

    public function checkIn(ReservationGroup $group): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'), 403);
        $this->ensureOwnership($group);

        try {
            $group = $this->groups->startCheckIn($group);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('admin.reservation-groups.show', $group)
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('check-ins.create', ['reservation_group_id' => $group->id])
            ->with('success', 'Reserva '.$group->code.' enviada a check-in. Completa los datos faltantes para registrar la estancia.');
    }

    private function ensureOwnership(ReservationGroup $group): void
    {
        abort_unless((int) $group->company_id === (int) auth()->user()?->company_id, 404);
    }

    private function occupancyUrlForGroup(ReservationGroup $group): string
    {
        $group->loadMissing('reservations.space.spaceMode');
        $reservation = $group->reservations->first();
        $space = $reservation?->space;
        $params = [
            'week_start' => $group->check_in?->toDateString() ?? today()->toDateString(),
        ];

        $params['view'] = $space?->spaceMode?->slug === 'compartido'
            ? 'shared:'.$space->id
            : 'private';

        return route('occupancy.index', $params);
    }
}
