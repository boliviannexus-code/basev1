<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckIns\ChangeStayRoomRequest;
use App\Models\Stay;
use App\Services\CheckIn\AvailableStayResourceService;
use App\Services\CheckIn\StayRoomMoveService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StayRoomChangeController extends Controller
{
    public function __construct(
        private readonly AvailableStayResourceService $resources,
        private readonly StayRoomMoveService $moves,
    ) {}

    public function create(Stay $stay): View
    {
        $this->ensureOwnership($stay);
        $stay->loadMissing(['space', 'room', 'bedUnit', 'accountStatement.items']);

        [$moveStart, $checkOut] = $this->moveRange($stay);

        return view('stays.room-change.create', [
            'stay' => $stay,
            'moveStart' => $moveStart,
            'checkOut' => $checkOut,
            'nights' => $moveStart->diffInDays($checkOut),
            'resources' => $moveStart->lt($checkOut)
                ? $this->resources->availableForMove($stay, $moveStart, $checkOut)
                : [],
            'currentResourceKey' => $this->resources->resourceKey($stay),
        ]);
    }

    public function store(ChangeStayRoomRequest $request, Stay $stay): RedirectResponse|JsonResponse
    {
        $this->ensureOwnership($stay);
        $newStay = $this->moves->move($stay, $request->validated(), $request->user());

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Habitacion cambiada correctamente a estancia #'.$newStay->id.'.',
                'refresh_occupancy' => true,
            ]);
        }

        return redirect()
            ->route('occupancy.index')
            ->with('success', 'Habitacion cambiada correctamente a estancia #'.$newStay->id.'.');
    }

    private function moveRange(Stay $stay): array
    {
        $today = CarbonImmutable::today();
        $checkIn = CarbonImmutable::parse($stay->check_in_date);
        $checkOut = CarbonImmutable::parse($stay->check_out_date);
        $moveStart = $today->lte($checkIn) ? $checkIn : $today;

        return [$moveStart, $checkOut];
    }

    private function ensureOwnership(Stay $stay): void
    {
        abort_unless((int) $stay->company_id === (int) auth()->user()?->company_id, 404);
    }
}
