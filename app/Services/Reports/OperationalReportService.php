<?php

namespace App\Services\Reports;

use App\Models\CashRegister;
use App\Models\CashRegisterExpense;
use App\Models\CashRegisterLodgingPayment;
use App\Models\ExtraChargeCategory;
use App\Models\OccupancyBlock;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Space;
use App\Models\SpaceRoom;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashRegister;
use App\Models\SpaceCashReservationPayment;
use App\Models\Stay;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OperationalReportService
{
    public function reportTypes(): array
    {
        return [
            'summary' => 'Resumen general',
            'sales' => 'Ventas POS',
            'expenses' => 'Egresos',
            'lodging' => 'Hospedaje',
            'reservations' => 'Reservas',
            'cash' => 'Cajas',
        ];
    }

    public function filters(array $input): array
    {
        $from = CarbonImmutable::parse($input['from'] ?? now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($input['to'] ?? now())->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [
            'type' => array_key_exists($input['type'] ?? '', $this->reportTypes()) ? $input['type'] : 'summary',
            'from' => $from,
            'to' => $to,
            'user_id' => filled($input['user_id'] ?? null) ? (int) $input['user_id'] : null,
            'payment_method_id' => filled($input['payment_method_id'] ?? null) ? (int) $input['payment_method_id'] : null,
            'category_id' => filled($input['category_id'] ?? null) ? (int) $input['category_id'] : null,
            'space_id' => filled($input['space_id'] ?? null) ? (int) $input['space_id'] : null,
            'occupancy_status' => filled($input['occupancy_status'] ?? null) ? (string) $input['occupancy_status'] : null,
            'section' => $input['section'] ?? null,
        ];
    }

    public function options(int $companyId): array
    {
        return [
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => PaymentMethod::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => ExtraChargeCategory::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function occupancyOptions(int $companyId): array
    {
        return [
            'users' => User::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'spaces' => Space::query()->where('company_id', $companyId)->where('status', 'active')->orderBy('title')->orderBy('name')->get(['id', 'title', 'name']),
            'occupancyStatuses' => [
                'occupied' => 'Ocupadas',
                'checked_out' => 'Check-out',
                'reserved' => 'Reservadas',
                'blocked' => 'Bloqueadas',
            ],
        ];
    }

    public function build(int $companyId, array $filters): array
    {
        $sales = $this->sales($companyId, $filters);
        $directIncomes = $this->directIncomes($companyId, $filters);
        $expenses = $this->expenses($companyId, $filters);
        $lodging = $filters['category_id'] ? collect() : $this->lodging($companyId, $filters);
        $reservations = $filters['category_id'] ? collect() : $this->reservations($companyId, $filters);
        $cash = $filters['category_id'] ? collect() : $this->cashRegisters($companyId, $filters);

        $incomeRows = collect()
            ->merge($sales->map(fn (Sale $sale): array => [
                'type' => 'Venta POS',
                'date' => $sale->sale_date,
                'responsible' => $sale->user?->name,
                'method' => $sale->payments->pluck('payment_method_name')->filter()->unique()->join(', '),
                'category' => '-',
                'detail' => $sale->receipt_number,
                'amount' => $this->saleAmount($sale, $filters),
            ]))
            ->merge($directIncomes->map(fn (SpaceCashIncome $income): array => [
                'type' => 'Ingreso directo',
                'date' => $income->received_at,
                'responsible' => $income->responsible_name ?: $income->user?->name,
                'method' => $income->paymentMethod?->name,
                'category' => $income->category?->name,
                'detail' => $income->detail,
                'amount' => (float) $income->amount,
            ]))
            ->merge($lodging->map(fn ($payment): array => [
                'type' => 'Hospedaje',
                'date' => $payment->created_at,
                'responsible' => $payment->user?->name,
                'method' => $payment->paymentMethod?->name,
                'category' => 'Hospedaje',
                'detail' => $payment->receipt_number,
                'amount' => (float) $payment->amount_bob,
            ]))
            ->merge($reservations->map(fn (SpaceCashReservationPayment $payment): array => [
                'type' => 'Reserva',
                'date' => $payment->created_at,
                'responsible' => $payment->user?->name,
                'method' => $payment->paymentMethod?->name,
                'category' => 'Reserva',
                'detail' => $payment->receipt_number,
                'amount' => (float) $payment->amount_bob,
            ]))
            ->sortByDesc('date')
            ->values();

        return [
            'sales' => $sales,
            'directIncomes' => $directIncomes,
            'expenses' => $expenses,
            'lodging' => $lodging,
            'reservations' => $reservations,
            'cashRegisters' => $cash,
            'incomeRows' => $incomeRows,
            'methodSummary' => $this->methodSummary($incomeRows, $expenses),
            'categorySummary' => $this->categorySummary($incomeRows, $expenses),
            'totals' => [
                'sales' => (float) $sales->sum(fn (Sale $sale): float => $this->saleAmount($sale, $filters)),
                'direct_incomes' => (float) $directIncomes->sum('amount'),
                'lodging' => (float) $lodging->sum('amount_bob'),
                'reservations' => (float) $reservations->sum('amount_bob'),
                'expenses' => (float) $expenses->sum('amount'),
                'cash_opening' => (float) $cash->sum('opening_amount'),
                'cash_closing' => (float) $cash->sum('closing_amount'),
            ],
        ];
    }

    private function sales(int $companyId, array $filters): Collection
    {
        return Sale::query()
            ->with(['user', 'payments', 'details.extraChargeCategory', 'cashRegister'])
            ->whereHas('cashRegister', fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereBetween('sale_date', [$filters['from'], $filters['to']])
            ->where('status', 'completed')
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->whereHas('payments', fn (Builder $payment) => $payment->where('payment_method_id', $id)))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->whereHas('details', fn (Builder $detail) => $detail->where('extra_charge_category_id', $id)))
            ->latest('sale_date')
            ->limit(500)
            ->get();
    }

    private function saleAmount(Sale $sale, array $filters): float
    {
        if ($filters['category_id']) {
            return (float) $sale->details
                ->where('extra_charge_category_id', $filters['category_id'])
                ->sum('subtotal');
        }

        if ($filters['payment_method_id']) {
            return (float) $sale->payments
                ->where('payment_method_id', $filters['payment_method_id'])
                ->sum('amount');
        }

        return (float) $sale->total;
    }

    private function directIncomes(int $companyId, array $filters): Collection
    {
        return SpaceCashIncome::query()
            ->with(['user', 'category', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->whereBetween('received_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->latest('received_at')
            ->limit(500)
            ->get();
    }

    private function expenses(int $companyId, array $filters): Collection
    {
        $pos = CashRegisterExpense::query()
            ->with(['user', 'category', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->whereBetween('spent_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->get()
            ->each(fn ($expense) => $expense->source_label = 'POS');

        $space = SpaceCashExpense::query()
            ->with(['user', 'category', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->whereBetween('spent_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->get()
            ->each(fn ($expense) => $expense->source_label = 'Hospedaje');

        return $pos->merge($space)->sortByDesc('spent_at')->take(500)->values();
    }

    private function lodging(int $companyId, array $filters): Collection
    {
        $pos = CashRegisterLodgingPayment::query()
            ->with(['user', 'stay.holderGuest', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->get()
            ->each(fn ($payment) => $payment->source_label = 'POS');

        $space = SpaceCashLodgingPayment::query()
            ->with(['user', 'stay.holderGuest', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->get()
            ->each(fn ($payment) => $payment->source_label = 'Hospedaje');

        return $pos->merge($space)->sortByDesc('created_at')->take(500)->values();
    }

    private function reservations(int $companyId, array $filters): Collection
    {
        return SpaceCashReservationPayment::query()
            ->with(['user', 'reservationGroup', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->latest('created_at')
            ->limit(500)
            ->get();
    }

    private function cashRegisters(int $companyId, array $filters): Collection
    {
        $pos = CashRegister::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->where('opened_at', '<=', $filters['to'])
            ->where(fn (Builder $query) => $query
                ->whereNull('closed_at')
                ->orWhere('closed_at', '>=', $filters['from']))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->get()
            ->each(fn ($register) => $register->source_label = 'POS');

        $space = SpaceCashRegister::query()
            ->with('user')
            ->where('company_id', $companyId)
            ->where('opened_at', '<=', $filters['to'])
            ->where(fn (Builder $query) => $query
                ->whereNull('closed_at')
                ->orWhere('closed_at', '>=', $filters['from']))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->get()
            ->each(fn ($register) => $register->source_label = 'Hospedaje');

        return $pos->merge($space)->sortByDesc('opened_at')->take(500)->values();
    }

    public function occupancy(int $companyId, array $filters): array
    {
        $stays = Stay::query()
            ->with(['space', 'room', 'bedUnit', 'holderGuest'])
            ->where('company_id', $companyId)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->whereDate('check_in_date', '<=', $filters['to']->toDateString())
            ->whereDate('check_out_date', '>', $filters['from']->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereHas('checkInGroup', fn (Builder $group) => $group->where('created_by', $id)))
            ->when(in_array($filters['occupancy_status'], ['occupied', 'checked_out'], true), fn (Builder $query) => $query->where('status', $filters['occupancy_status']))
            ->latest('check_in_date')
            ->limit(500)
            ->get();

        $reservations = Reservation::query()
            ->with(['space', 'room', 'reservationChannel'])
            ->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->whereDate('check_in', '<=', $filters['to']->toDateString())
            ->whereDate('check_out', '>', $filters['from']->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['occupancy_status'] && $filters['occupancy_status'] !== 'reserved', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('check_in')
            ->limit(500)
            ->get();

        $blocks = OccupancyBlock::query()
            ->with(['space', 'room', 'bedUnit', 'creator'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $filters['to']->toDateString())
            ->whereDate('end_date', '>=', $filters['from']->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->when($filters['occupancy_status'] && $filters['occupancy_status'] !== 'blocked', fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->latest('start_date')
            ->limit(500)
            ->get();

        $spaces = Space::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->get(['id', 'title', 'name', 'max_capacity']);

        $periodNights = max(1, $filters['from']->startOfDay()->diffInDays($filters['to']->startOfDay()) + 1);
        $capacityNights = max(0, (int) $spaces->sum(fn (Space $space): int => max(1, (int) ($space->max_capacity ?: 1))) * $periodNights);
        $occupiedPersonNights = (int) $stays->sum(fn (Stay $stay): int => $this->overlapNights($stay->check_in_date, $stay->check_out_date, $filters) * max(1, (int) $stay->people_count));
        $reservedPersonNights = (int) $reservations->sum(fn (Reservation $reservation): int => $this->overlapNights($reservation->check_in, $reservation->check_out, $filters) * max(1, (int) $reservation->guests));
        $blockedNights = (int) $blocks->sum(fn (OccupancyBlock $block): int => $this->inclusiveOverlapDays($block->start_date, $block->end_date, $filters));

        return [
            'stays' => $stays,
            'reservations' => $reservations,
            'blocks' => $blocks,
            'summary' => [
                'spaces' => $spaces->count(),
                'capacity_nights' => $capacityNights,
                'occupied_person_nights' => $occupiedPersonNights,
                'reserved_person_nights' => $reservedPersonNights,
                'blocked_nights' => $blockedNights,
                'occupancy_rate' => $capacityNights > 0 ? round(($occupiedPersonNights / $capacityNights) * 100, 2) : 0,
            ],
        ];
    }

    public function dailyOccupancy(int $companyId, CarbonImmutable $date, array $filters): array
    {
        $reportDate = $date->startOfDay();
        $previousDate = $reportDate->subDay();

        $rooms = SpaceRoom::query()
            ->with('space')
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereHas('space', fn (Builder $query) => $query->where('status', 'active'))
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->orderBy('space_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $spaceOnlyRows = Space::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->whereDoesntHave('rooms', fn (Builder $query) => $query->where('status', 'active'))
            ->orderBy('title')
            ->orderBy('name')
            ->get();

        $stays = Stay::query()
            ->with(['space', 'room', 'bedUnit', 'holderGuest', 'accountStatement'])
            ->where('company_id', $companyId)
            ->where('status', 'occupied')
            ->whereDate('check_in_date', '<=', $reportDate->toDateString())
            ->whereDate('check_out_date', '>', $reportDate->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereHas('checkInGroup', fn (Builder $group) => $group->where('created_by', $id)))
            ->get();

        $reservations = Reservation::query()
            ->with(['space', 'room', 'roomItems'])
            ->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->whereDate('check_in', '<=', $reportDate->toDateString())
            ->whereDate('check_out', '>', $reportDate->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->get();

        $blocks = OccupancyBlock::query()
            ->with(['space', 'room', 'bedUnit'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $reportDate->toDateString())
            ->whereDate('end_date', '>=', $reportDate->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->get();

        $roomRows = $rooms
            ->map(fn (SpaceRoom $room): array => $this->dailyRoomRow($room, $stays, $reservations, $blocks))
            ->merge($spaceOnlyRows->map(fn (Space $space): array => $this->dailySpaceRow($space, $stays, $reservations, $blocks)))
            ->values();

        $breakfastRows = Stay::query()
            ->with(['space', 'room', 'holderGuest'])
            ->where('company_id', $companyId)
            ->where('breakfast_included', true)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->whereDate('check_in_date', '<=', $previousDate->toDateString())
            ->whereDate('check_out_date', '>', $previousDate->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereHas('checkInGroup', fn (Builder $group) => $group->where('created_by', $id)))
            ->orderBy('space_id')
            ->orderBy('space_room_id')
            ->get()
            ->map(fn (Stay $stay): array => [
                'space' => $stay->space?->title ?: $stay->space?->name ?: '-',
                'room' => $stay->room?->title ?: $stay->room?->name ?: 'Sin habitacion',
                'guest' => $stay->holderGuest?->full_name ?? '-',
                'people' => max(1, (int) $stay->people_count),
            ])
            ->values();

        return [
            'date' => $reportDate,
            'previousDate' => $previousDate,
            'rooms' => $roomRows,
            'breakfasts' => $breakfastRows,
            'summary' => [
                'rooms' => $roomRows->count(),
                'free' => $roomRows->where('status', 'free')->count(),
                'occupied' => $roomRows->where('status', 'occupied')->count(),
                'reserved' => $roomRows->where('status', 'reserved')->count(),
                'blocked' => $roomRows->where('status', 'blocked')->count(),
                'people' => (int) $roomRows->sum('people'),
                'breakfasts' => (int) $breakfastRows->sum('people'),
                'balance' => (float) $roomRows->sum('balance'),
            ],
        ];
    }

    private function dailyRoomRow(SpaceRoom $room, Collection $stays, Collection $reservations, Collection $blocks): array
    {
        $roomStays = $stays->filter(fn (Stay $stay): bool => (int) $stay->space_room_id === (int) $room->id)->values();
        $roomReservations = $reservations->filter(fn (Reservation $reservation): bool => $this->reservationMatchesRoom($reservation, $room))->values();
        $block = ($roomStays->isNotEmpty() || $roomReservations->isNotEmpty()) ? null : $blocks->first(fn (OccupancyBlock $block): bool => (int) $block->space_room_id === (int) $room->id);

        if ($roomStays->isNotEmpty()) {
            return $this->dailyStayGroupRow(
                $room->space?->title ?: $room->space?->name ?: '-',
                $room->title ?: $room->name ?: 'Habitacion #'.$room->id,
                max(1, (int) ($room->max_capacity ?: 1)),
                $roomStays,
            );
        }

        if ($roomReservations->isNotEmpty()) {
            return $this->dailyReservationGroupRow(
                $room->space?->title ?: $room->space?->name ?: '-',
                $room->title ?: $room->name ?: 'Habitacion #'.$room->id,
                max(1, (int) ($room->max_capacity ?: 1)),
                $roomReservations,
            );
        }

        return $this->dailyResourceRow(
            $room->space?->title ?: $room->space?->name ?: '-',
            $room->title ?: $room->name ?: 'Habitacion #'.$room->id,
            max(1, (int) ($room->max_capacity ?: 1)),
            null,
            null,
            $block,
        );
    }

    private function dailySpaceRow(Space $space, Collection $stays, Collection $reservations, Collection $blocks): array
    {
        $stay = $stays->first(fn (Stay $stay): bool => (int) $stay->space_id === (int) $space->id && blank($stay->space_room_id));
        $reservation = $stay ? null : $reservations->first(fn (Reservation $reservation): bool => (int) $reservation->space_id === (int) $space->id && blank($reservation->space_room_id));
        $block = ($stay || $reservation) ? null : $blocks->first(fn (OccupancyBlock $block): bool => (int) $block->space_id === (int) $space->id && blank($block->space_room_id));

        return $this->dailyResourceRow(
            $space->title ?: $space->name ?: '-',
            'Unidad completa',
            max(1, (int) ($space->max_capacity ?: 1)),
            $stay,
            $reservation,
            $block,
        );
    }

    private function dailyResourceRow(string $space, string $room, int $capacity, ?Stay $stay, ?Reservation $reservation, ?OccupancyBlock $block): array
    {
        if ($stay) {
            $statement = $stay->accountStatement;
            $total = (float) (($statement?->subtotal ?? 0) + ($statement?->extra_charges_total ?? 0) - ($statement?->discount_total ?? 0));

            return [
                'status' => 'occupied',
                'status_label' => 'Ocupada',
                'space' => $space,
                'room' => $room,
                'guest' => $stay->holderGuest?->full_name ?? '-',
                'check_in' => $stay->check_in_date,
                'check_out' => $stay->check_out_date,
                'people' => max(1, (int) $stay->people_count),
                'capacity' => $capacity,
                'payment_status' => $this->paymentStatusLabel($statement?->status),
                'total' => $total,
                'advance' => (float) ($statement?->payments_total ?? 0),
                'balance' => (float) ($statement?->balance ?? 0),
                'currency' => $statement?->currency ?: $stay->currency,
                'breakfast' => $stay->breakfast_included ? max(1, (int) $stay->people_count) : 0,
            ];
        }

        if ($reservation) {
            return [
                'status' => 'reserved',
                'status_label' => 'Reservada',
                'space' => $space,
                'room' => $room,
                'guest' => $reservation->guest_name ?: '-',
                'check_in' => $reservation->check_in,
                'check_out' => $reservation->check_out,
                'people' => max(1, (int) $reservation->guests),
                'capacity' => $capacity,
                'payment_status' => $this->paymentStatusLabel($reservation->payment_status),
                'total' => (float) $reservation->total_amount,
                'advance' => (float) $reservation->advance_amount,
                'balance' => (float) $reservation->balance_amount,
                'currency' => $reservation->currency,
                'breakfast' => $reservation->breakfast_included ? max(1, (int) $reservation->guests) : 0,
            ];
        }

        if ($block) {
            return [
                'status' => 'blocked',
                'status_label' => 'Bloqueada',
                'space' => $space,
                'room' => $room,
                'guest' => $block->title ?: $block->description ?: $block->type ?: '-',
                'check_in' => $block->start_date,
                'check_out' => $block->end_date,
                'people' => 0,
                'capacity' => $capacity,
                'payment_status' => '-',
                'total' => 0.0,
                'advance' => 0.0,
                'balance' => 0.0,
                'currency' => 'BOB',
                'breakfast' => 0,
            ];
        }

        return [
            'status' => 'free',
            'status_label' => 'Libre',
            'space' => $space,
            'room' => $room,
            'guest' => '',
            'check_in' => null,
            'check_out' => null,
            'people' => 0,
            'capacity' => $capacity,
            'payment_status' => '',
            'total' => 0.0,
            'advance' => 0.0,
            'balance' => 0.0,
            'currency' => 'BOB',
            'breakfast' => 0,
        ];
    }

    private function dailyStayGroupRow(string $space, string $room, int $capacity, Collection $stays): array
    {
        $people = (int) $stays->sum(fn (Stay $stay): int => max(1, (int) $stay->people_count));
        $statements = $stays->pluck('accountStatement')->filter();
        $total = (float) $statements->sum(fn ($statement): float => (float) (($statement->subtotal ?? 0) + ($statement->extra_charges_total ?? 0) - ($statement->discount_total ?? 0)));
        $advance = (float) $statements->sum('payments_total');
        $balance = (float) $statements->sum('balance');
        $statuses = $statements->pluck('status')->filter()->unique()->values();
        $currencies = $statements->pluck('currency')->filter()->unique()->values();

        return [
            'status' => 'occupied',
            'status_label' => 'Ocupada',
            'space' => $space,
            'room' => $room,
            'guest' => $stays->map(fn (Stay $stay): string => $stay->holderGuest?->full_name ?? '-')->filter(fn (string $name): bool => $name !== '-')->unique()->join(', ') ?: '-',
            'check_in' => $stays->min('check_in_date'),
            'check_out' => $stays->max('check_out_date'),
            'people' => $people,
            'capacity' => $capacity,
            'payment_status' => $statuses->count() === 1 ? $this->paymentStatusLabel($statuses->first()) : ($statuses->isNotEmpty() ? 'Mixto' : '-'),
            'total' => $total,
            'advance' => $advance,
            'balance' => $balance,
            'currency' => $currencies->count() === 1 ? $currencies->first() : 'BOB',
            'breakfast' => (int) $stays->sum(fn (Stay $stay): int => $stay->breakfast_included ? max(1, (int) $stay->people_count) : 0),
        ];
    }

    private function dailyReservationGroupRow(string $space, string $room, int $capacity, Collection $reservations): array
    {
        $people = (int) $reservations->sum(fn (Reservation $reservation): int => max(1, (int) $reservation->guests));
        $statuses = $reservations->pluck('payment_status')->filter()->unique()->values();
        $currencies = $reservations->pluck('currency')->filter()->unique()->values();

        return [
            'status' => 'reserved',
            'status_label' => 'Reservada',
            'space' => $space,
            'room' => $room,
            'guest' => $reservations->pluck('guest_name')->filter()->unique()->join(', ') ?: '-',
            'check_in' => $reservations->min('check_in'),
            'check_out' => $reservations->max('check_out'),
            'people' => $people,
            'capacity' => $capacity,
            'payment_status' => $statuses->count() === 1 ? $this->paymentStatusLabel($statuses->first()) : ($statuses->isNotEmpty() ? 'Mixto' : '-'),
            'total' => (float) $reservations->sum('total_amount'),
            'advance' => (float) $reservations->sum('advance_amount'),
            'balance' => (float) $reservations->sum('balance_amount'),
            'currency' => $currencies->count() === 1 ? $currencies->first() : 'BOB',
            'breakfast' => (int) $reservations->sum(fn (Reservation $reservation): int => $reservation->breakfast_included ? max(1, (int) $reservation->guests) : 0),
        ];
    }

    private function reservationMatchesRoom(Reservation $reservation, SpaceRoom $room): bool
    {
        return (int) $reservation->space_room_id === (int) $room->id
            || $reservation->roomItems->contains(fn ($item): bool => (int) $item->space_room_id === (int) $room->id);
    }

    private function paymentStatusLabel(?string $status): string
    {
        return [
            'pending' => 'Pendiente',
            'partial' => 'Parcial',
            'paid' => 'Pagado',
            'submitted' => 'Enviado',
            'validated' => 'Validado',
            'rejected' => 'Rechazado',
        ][$status ?? ''] ?? ($status ? str($status)->replace('_', ' ')->title()->toString() : '-');
    }

    private function overlapNights($start, $end, array $filters): int
    {
        $startDate = CarbonImmutable::parse($start)->max($filters['from']->startOfDay());
        $endDate = CarbonImmutable::parse($end)->min($filters['to']->copy()->addDay()->startOfDay());

        return max(0, (int) $startDate->diffInDays($endDate));
    }

    private function inclusiveOverlapDays($start, $end, array $filters): int
    {
        $startDate = CarbonImmutable::parse($start)->max($filters['from']->startOfDay());
        $endDate = CarbonImmutable::parse($end)->min($filters['to']->startOfDay());

        return max(0, (int) $startDate->diffInDays($endDate) + 1);
    }

    private function methodSummary(Collection $incomeRows, Collection $expenses): Collection
    {
        $income = $incomeRows->groupBy(fn (array $row): string => $row['method'] ?: 'Sin metodo');
        $expense = $expenses->groupBy(fn ($expense): string => $expense->paymentMethod?->name ?: 'Efectivo');
        $names = $income->keys()->merge($expense->keys())->unique()->sort()->values();

        return $names->map(fn (string $name): array => [
            'name' => $name,
            'income' => (float) ($income->get($name)?->sum('amount') ?? 0),
            'expenses' => (float) ($expense->get($name)?->sum('amount') ?? 0),
            'balance' => (float) ($income->get($name)?->sum('amount') ?? 0) - (float) ($expense->get($name)?->sum('amount') ?? 0),
        ]);
    }

    private function categorySummary(Collection $incomeRows, Collection $expenses): Collection
    {
        $income = $incomeRows->groupBy(fn (array $row): string => $row['category'] ?: 'Sin categoria');
        $expense = $expenses->groupBy(fn ($expense): string => $expense->category?->name ?: 'Sin categoria');
        $names = $income->keys()->merge($expense->keys())->unique()->sort()->values();

        return $names->map(fn (string $name): array => [
            'name' => $name,
            'income' => (float) ($income->get($name)?->sum('amount') ?? 0),
            'expenses' => (float) ($expense->get($name)?->sum('amount') ?? 0),
            'balance' => (float) ($income->get($name)?->sum('amount') ?? 0) - (float) ($expense->get($name)?->sum('amount') ?? 0),
        ]);
    }
}
