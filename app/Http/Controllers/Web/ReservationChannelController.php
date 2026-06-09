<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservationChannels\StoreReservationChannelRequest;
use App\Http\Requests\ReservationChannels\UpdateReservationChannelRequest;
use App\Models\ReservationChannel;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReservationChannelController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('reservation-channels.manage'), 403);

        $type = $request->query('type');
        $channels = ReservationChannel::query()
            ->where('company_id', $this->companyId())
            ->when(filled($type), fn (Builder $query): Builder => $query->where('type', $type))
            ->withCount('reservations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('reservation-channels.index', [
            'channels' => $channels,
            'channel' => new ReservationChannel(['is_active' => true, 'type' => 'direct']),
            'type' => $type,
            'types' => ReservationChannel::TYPES,
        ]);
    }

    public function store(StoreReservationChannelRequest $request): RedirectResponse|JsonResponse
    {
        ReservationChannel::query()->create([
            'company_id' => $this->companyId(),
            ...$request->validated(),
        ]);

        return $this->response($request, 'Canal de reserva creado correctamente.');
    }

    public function update(UpdateReservationChannelRequest $request, ReservationChannel $reservationChannel): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($reservationChannel);
        $data = $request->validated();

        if ($reservationChannel->is_protected) {
            $data['is_active'] = true;
        }

        $reservationChannel->update($data);

        return $this->response($request, 'Canal de reserva actualizado correctamente.');
    }

    public function toggle(ReservationChannel $reservationChannel): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reservation-channels.manage'), 403);
        $this->ensureOwnership($reservationChannel);

        if ($reservationChannel->is_protected) {
            return back()->withErrors(['channel' => 'Este canal base debe permanecer activo.']);
        }

        $reservationChannel->update(['is_active' => ! $reservationChannel->is_active]);

        return back()->with('success', 'Estado del canal actualizado.');
    }

    public function destroy(ReservationChannel $reservationChannel): RedirectResponse
    {
        abort_unless(auth()->user()?->can('reservation-channels.manage'), 403);
        $this->ensureOwnership($reservationChannel);

        if ($reservationChannel->is_protected) {
            return back()->withErrors(['channel' => 'Este canal base no se puede eliminar.']);
        }

        if ($reservationChannel->reservations()->exists()) {
            return back()->withErrors(['channel' => 'No se puede eliminar un canal que ya tiene reservas asociadas.']);
        }

        $reservationChannel->delete();

        return back()->with('success', 'Canal de reserva eliminado correctamente.');
    }

    private function ensureOwnership(ReservationChannel $channel): void
    {
        abort_unless((int) $channel->company_id === $this->companyId(), 403);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }

    private function response(Request $request, string $message): RedirectResponse|JsonResponse
    {
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'refresh_url' => route('reservation-channels.index'),
            ]);
        }

        return redirect()
            ->route('reservation-channels.index')
            ->with('success', $message);
    }
}
