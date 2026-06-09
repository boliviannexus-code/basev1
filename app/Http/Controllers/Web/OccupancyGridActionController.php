<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ExtraChargeCategory;
use App\Models\Stay;
use App\Services\CheckIn\CheckOutService;
use App\Services\Occupancy\OccupancyGridActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OccupancyGridActionController extends Controller
{
    public function __construct(
        private readonly OccupancyGridActionService $actions,
        private readonly CheckOutService $checkOuts,
    ) {}

    public function getCellActions(Request $request): JsonResponse
    {
        Gate::authorize('occupancy.manage');

        $context = $this->cellContext($request);

        return response()->json([
            'html' => view('occupancy.partials.cell-actions', $context)->render(),
            'actions' => $context['actions'],
            'occupancy_state' => $context['occupancy_state'],
        ]);
    }

    public function openCheckIn(Request $request): View
    {
        return $this->modal($request, OccupancyGridActionService::ACTION_CHECK_IN, 'Check-in');
    }

    public function openCheckInSummary(Request $request): View
    {
        Gate::authorize('occupancy.manage');

        $context = $this->cellContext($request);
        $stay = $context['stay'] ?? null;

        abort_unless($stay, 404);

        $stay->loadMissing([
            'checkInGroup.mainGuest.birthCountry',
            'checkInGroup.reservationChannel',
            'checkInGroup.stays.holderGuest.birthCountry',
            'checkInGroup.stays.guests.birthCountry',
            'checkInGroup.stays.space',
            'checkInGroup.stays.room',
            'checkInGroup.stays.bedUnit',
            'checkInGroup.stays.accountStatement.items.extraChargeCategory',
            'holderGuest.birthCountry',
            'guests.birthCountry',
            'space',
            'room',
            'bedUnit',
            'accountStatement.items.extraChargeCategory',
        ]);

        return view('occupancy.partials.check-in-summary-modal', [
            ...$context,
            'stay' => $stay,
            'group' => $stay->checkInGroup,
            'statement' => $stay->accountStatement,
        ]);
    }

    public function openCheckOut(Request $request): View
    {
        Gate::authorize('occupancy.manage');

        $context = $this->cellContext($request);
        $stay = $context['stay'] ?? null;

        if (! $stay) {
            throw ValidationException::withMessages([
                'date' => 'No hay una estancia activa para realizar check-out.',
            ]);
        }

        $this->actions->ensureActionAllowed(
            OccupancyGridActionService::ACTION_CHECK_OUT,
            $context['date'],
            $context['availability_status'] ?? null,
            $context['occupancy_state'] ?? null,
            $stay,
        );

        return view('occupancy.partials.check-out-modal', [
            ...$context,
            'stay' => $stay,
            'group' => $stay->checkInGroup,
            'summary' => $this->checkOuts->debtSummary($stay),
        ]);
    }

    public function completeCheckOut(Request $request, Stay $stay): JsonResponse
    {
        Gate::authorize('occupancy.manage');
        abort_unless((int) $stay->company_id === $this->companyId(), 404);

        try {
            $this->checkOuts->complete($stay, $request->user());
        } catch (ValidationException $exception) {
            return response()->json([
                'success' => false,
                'message' => collect($exception->errors())->flatten()->first() ?: 'No se pudo realizar el check-out.',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Check-out realizado correctamente.',
        ]);
    }

    public function openReservation(Request $request): View
    {
        return $this->modal($request, OccupancyGridActionService::ACTION_RESERVATION, 'Reserva');
    }

    public function openBlock(Request $request): View
    {
        return $this->modal($request, OccupancyGridActionService::ACTION_BLOCK, 'Bloqueo');
    }

    public function openExtraCharge(Request $request): View
    {
        Gate::authorize('occupancy.manage');

        $context = $this->cellContext($request);
        $stay = $context['stay'] ?? null;
        $reservation = $context['reservation'] ?? null;

        abort_unless($stay || $reservation, 404);

        ExtraChargeCategory::ensureDefaultsForCompany($this->companyId());

        return view('extra-charges.partials.form', [
            ...$context,
            'targetType' => $stay ? 'stay' : 'reservation',
            'target' => $stay ?: $reservation,
            'action' => $stay
                ? route('stays.extra-charges.store', $stay)
                : route('admin.reservations.extra-charges.store', $reservation),
            'categories' => ExtraChargeCategory::query()
                ->where('company_id', $this->companyId())
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    private function modal(Request $request, string $action, string $title): View
    {
        Gate::authorize('occupancy.manage');

        $context = $this->cellContext($request);
        $this->actions->ensureActionAllowed($action, $context['date'], $context['availability_status'] ?? null, $context['occupancy_state'] ?? null, $context['stay'] ?? null);

        return view('occupancy.partials.action-modal', [
            ...$context,
            'action' => $action,
            'title' => $title,
        ]);
    }

    private function cellContext(Request $request): array
    {
        $data = $request->validate([
            'space_id' => ['required', 'integer'],
            'space_room_id' => ['nullable', 'integer'],
            'room_bed_unit_id' => ['nullable', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        return $this->actions->cellContext($this->companyId(), $data);
    }

    private function companyId(): int
    {
        return (int) auth()->user()?->company_id;
    }
}
