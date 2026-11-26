<?php

namespace App\Services\Reports;

use App\Models\AccountStatementItem;
use App\Models\ExtraChargeCategory;
use App\Models\OccupancyBlock;
use App\Models\PaymentMethod;
use App\Models\Reservation;
use App\Models\RoomBedUnit;
use App\Models\Space;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashReservationPayment;
use App\Models\SpaceRoom;
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
            'expenses' => 'Egresos',
            'lodging' => 'Hospedaje',
            'reservations' => 'Reservas',
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
            'spaces' => Space::query()->where('company_id', $companyId)->orderBy('title')->orderBy('name')->get(['id', 'title', 'name']),
        ];
    }

    public function occupancyOptions(int $companyId): array
    {
        return [
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
        $stayExtraCharges = $this->stayExtraCharges($companyId, $filters);
        $directIncomes = $this->directIncomes($companyId, $filters);
        $expenses = $this->expenses($companyId, $filters);
        $lodging = $filters['category_id'] ? collect() : $this->lodging($companyId, $filters);
        $reservations = $filters['category_id'] ? collect() : $this->reservations($companyId, $filters);
        $discounts = $filters['payment_method_id'] || $filters['category_id'] ? collect() : $this->discounts($companyId, $filters);
        $incomeRows = collect()
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
            'selectedSpace' => $filters['space_id']
                ? Space::query()->where('company_id', $companyId)->find($filters['space_id'])
                : null,
            'stayExtraCharges' => $stayExtraCharges,
            'stayExtraChargeTotals' => $stayExtraCharges->groupBy('currency')->map(fn (Collection $items): float => (float) $items->sum('total')),
            'directIncomes' => $directIncomes,
            'expenses' => $expenses,
            'lodging' => $lodging,
            'reservations' => $reservations,
            'discounts' => $discounts,
            'incomeRows' => $incomeRows,
            'methodSummary' => $this->methodSummary($incomeRows, $expenses),
            'categorySummary' => $this->categorySummary($incomeRows, $expenses),
            'discountsByUser' => $this->discountsByUser($discounts),
            'totals' => [
                'direct_incomes' => (float) $directIncomes->sum('amount'),
                'lodging' => (float) $lodging->sum('amount_bob'),
                'reservations' => (float) $reservations->sum('amount_bob'),
                'expenses' => (float) $expenses->sum('amount'),
                'discounts' => (float) $discounts->sum(fn (AccountStatementItem $discount): float => abs((float) $discount->total)),
            ],
        ];
    }

    public function statistics(int $companyId, array $filters): array
    {
        $filters['statistics'] = true;
        $direct = $this->directIncomes($companyId, $filters);
        $expenses = $this->expenses($companyId, $filters);
        $lodging = $filters['category_id'] ? collect() : $this->lodging($companyId, $filters);
        $reservations = $filters['category_id'] ? collect() : $this->reservations($companyId, $filters);
        $incomeRows = collect()
            ->merge($direct->map(fn ($item): array => ['date' => $item->received_at, 'amount' => (float) $item->amount, 'method' => $item->paymentMethod?->name]))
            ->merge($lodging->map(fn ($item): array => ['date' => $item->created_at, 'amount' => (float) $item->amount_bob, 'method' => $item->paymentMethod?->name]))
            ->merge($reservations->map(fn ($item): array => ['date' => $item->created_at, 'amount' => (float) $item->amount_bob, 'method' => $item->paymentMethod?->name]));
        $incomeByDate = $incomeRows->groupBy(fn (array $row): string => $row['date']->toDateString());
        $expensesByDate = $expenses->groupBy(fn ($item): string => $item->spent_at->toDateString());
        $daily = collect(\Carbon\CarbonPeriod::create($filters['from']->startOfDay(), $filters['to']->startOfDay()))
            ->map(fn ($day): array => [
                'date' => $day->toDateString(),
                'income' => (float) ($incomeByDate->get($day->toDateString())?->sum('amount') ?? 0),
                'expenses' => (float) ($expensesByDate->get($day->toDateString())?->sum('amount') ?? 0),
            ])->values();
        $income = (float) $incomeRows->sum('amount');
        $expense = (float) $expenses->sum('amount');

        return [
            'daily' => $daily,
            'income' => $income,
            'expenses' => $expense,
            'balance' => $income - $expense,
            'exceeds_detail_limit' => max($direct->count(), $expenses->count(), $lodging->count(), $reservations->count()) > 500,
            'sources' => [
                ['name' => 'Ingresos directos', 'total' => (float) $direct->sum('amount')],
                ['name' => 'Hospedaje', 'total' => (float) $lodging->sum('amount_bob')],
                ['name' => 'Reservas', 'total' => (float) $reservations->sum('amount_bob')],
            ],
            'methods' => $this->methodSummary($incomeRows, $expenses),
        ];
    }

    private function stayExtraCharges(int $companyId, array $filters): Collection
    {
        return AccountStatementItem::query()
            ->with(['creator', 'extraChargeCategory', 'stay.space', 'stay.room', 'stay.holderGuest'])
            ->where('company_id', $companyId)
            ->where('type', 'extra')
            ->where('status', 'active')
            ->whereHas('stay', fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereBetween('date', [$filters['from']->toDateString(), $filters['to']->toDateString()])
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereHas('stay', fn (Builder $stay) => $stay->where('space_id', $id)))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    private function directIncomes(int $companyId, array $filters): Collection
    {
        return SpaceCashIncome::query()
            ->with(['user', 'category', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->when($filters['space_id'], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereBetween('received_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->latest('received_at')
            ->when(empty($filters['statistics']), fn (Builder $query): Builder => $query->limit(500))
            ->get();
    }

    private function expenses(int $companyId, array $filters): Collection
    {
        return SpaceCashExpense::query()
            ->with(['user', 'category', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->when($filters['space_id'], fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->whereBetween('spent_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->when($filters['category_id'], fn (Builder $query, int $id) => $query->where('extra_charge_category_id', $id))
            ->latest('spent_at')
            ->when(empty($filters['statistics']), fn (Builder $query): Builder => $query->limit(500))
            ->get();
    }

    private function lodging(int $companyId, array $filters): Collection
    {
        return SpaceCashLodgingPayment::query()
            ->with(['user', 'stay.holderGuest', 'stay.space', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereHas('stay', fn (Builder $stay) => $stay->where('space_id', $id)))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->latest('created_at')
            ->when(empty($filters['statistics']), fn (Builder $query): Builder => $query->limit(500))
            ->get();
    }

    private function reservations(int $companyId, array $filters): Collection
    {
        return SpaceCashReservationPayment::query()
            ->with(['user', 'reservationGroup.reservations.space', 'paymentMethod'])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereHas('reservationGroup.reservations', fn (Builder $reservation) => $reservation->where('space_id', $id)))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('user_id', $id))
            ->when($filters['payment_method_id'], fn (Builder $query, int $id) => $query->where('payment_method_id', $id))
            ->latest('created_at')
            ->when(empty($filters['statistics']), fn (Builder $query): Builder => $query->limit(500))
            ->get();
    }

    private function discounts(int $companyId, array $filters): Collection
    {
        return AccountStatementItem::query()
            ->with(['creator', 'stay.space', 'stay.holderGuest'])
            ->where('company_id', $companyId)
            ->where('type', 'discount')
            ->where('status', 'active')
            ->whereBetween('created_at', [$filters['from'], $filters['to']])
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->where('created_by', $id))
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereHas('stay', fn (Builder $stay) => $stay->where('space_id', $id)))
            ->latest()
            ->limit(500)
            ->get();
    }

    private function discountsByUser(Collection $discounts): Collection
    {
        return $discounts
            ->groupBy(fn (AccountStatementItem $discount): string => $discount->creator?->name ?: 'Sin usuario')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'discounts_count' => $rows->count(),
                'total' => $rows->sum(fn (AccountStatementItem $discount): float => abs((float) $discount->total)),
            ])
            ->values();
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

        $hotelSummary = $this->hotelPeriodSummary($companyId, $filters);

        return [
            'stays' => $stays,
            'reservations' => $reservations,
            'blocks' => $blocks,
            'hotel_summary' => $hotelSummary,
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

    private function hotelPeriodSummary(int $companyId, array $filters): Collection
    {
        $from = $filters['from']->startOfDay();
        $to = $filters['to']->startOfDay();
        $rooms = SpaceRoom::query()->with('space')->where('company_id', $companyId)->where('status', 'active')
            ->whereHas('space', fn (Builder $query) => $query->where('status', 'active'))
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))->get();
        $spaces = Space::query()->where('company_id', $companyId)->where('status', 'active')
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->whereDoesntHave('rooms', fn (Builder $query) => $query->where('status', 'active'))->get();
        $stays = Stay::query()->with(['holderGuest', 'accountStatement'])->where('company_id', $companyId)
            ->whereIn('status', ['occupied', 'checked_out'])->whereDate('check_in_date', '<=', $to->toDateString())
            ->whereDate('check_out_date', '>', $from->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))->get();
        $reservations = Reservation::query()->with('roomItems')->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))->get();
        $blocks = OccupancyBlock::query()->where('company_id', $companyId)->where('status', 'active')
            ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))->get();

        $totals = collect();
        foreach (\Carbon\CarbonPeriod::create($from, $to) as $date) {
            $day = $date->format('Y-m-d');
            $dayStays = $stays->filter(fn (Stay $stay): bool => $stay->check_in_date->toDateString() <= $day
                && $stay->check_out_date->toDateString() > $day
                && ($stay->status === 'occupied' || ($stay->checked_out_at && $stay->checked_out_at->toDateString() > $day)));
            $dayReservations = $reservations->filter(fn (Reservation $reservation): bool => $reservation->check_in->toDateString() <= $day && $reservation->check_out->toDateString() > $day);
            $dayBlocks = $blocks->filter(fn (OccupancyBlock $block): bool => $block->start_date->toDateString() <= $day && $block->end_date->toDateString() >= $day);
            $rows = $rooms->map(fn (SpaceRoom $room): array => ['space_id' => $room->space_id, ...$this->dailyRoomRow($room, $dayStays, $dayReservations, $dayBlocks)])
                ->merge($spaces->map(fn (Space $space): array => ['space_id' => $space->id, ...$this->dailySpaceRow($space, $dayStays, $dayReservations, $dayBlocks)]));
            foreach ($rows as $row) {
                $id = $row['space_id'];
                $total = $totals->get($id, ['name' => $row['space'], 'occupied' => 0, 'free' => 0, 'people' => 0, 'units' => 0]);
                $total['units']++;
                $total['occupied'] += (int) ($row['status'] === 'occupied');
                $total['free'] += (int) ($row['status'] === 'free');
                $total['people'] += $row['status'] === 'occupied' ? (int) $row['people'] : 0;
                $totals->put($id, $total);
            }
        }

        return $totals->map(function (array $total): array {
            $total['rate'] = $total['units'] ? round($total['occupied'] * 100 / $total['units'], 2) : 0;
            return $total;
        })->values();
    }

    public function dailyOccupancy(int $companyId, CarbonImmutable $date, array $filters): array
    {
        $reportDate = $date->startOfDay();

        $rooms = SpaceRoom::query()
            ->with(['space', 'bedUnits' => fn ($query) => $query->where('status', 'active')->orderBy('sort_order')->orderBy('id')])
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
            ->where(function (Builder $query) use ($reportDate): void {
                $query->where('status', 'occupied')
                    ->orWhere(fn (Builder $query) => $query->where('status', 'checked_out')->whereDate('checked_out_at', '>', $reportDate->toDateString()));
            })
            ->whereDate('check_in_date', '<=', $reportDate->toDateString())
            ->whereDate('check_out_date', '>', $reportDate->toDateString())
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
            ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereHas('checkInGroup', fn (Builder $group) => $group->where('created_by', $id)))
            ->get();

        $reservations = Reservation::query()
            ->with(['space', 'room', 'roomItems', 'bedUnitItems'])
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
            ->flatMap(fn (SpaceRoom $room): array => $this->dailyRoomRows($room, $stays, $reservations, $blocks))
            ->merge($spaceOnlyRows->map(fn (Space $space): array => ['space_id' => $space->id, 'room_id' => null, 'bed_id' => null, ...$this->dailySpaceRow($space, $stays, $reservations, $blocks)]))
            ->values();

        if ($reportDate->isSameDay(today())) {
            $pendingStays = Stay::query()
                ->with(['space', 'room', 'bedUnit', 'holderGuest', 'accountStatement'])
                ->where('company_id', $companyId)
                ->where('status', 'occupied')
                ->whereDate('check_in_date', '<=', $reportDate->toDateString())
                ->whereDate('check_out_date', '<=', $reportDate->toDateString())
                ->when($filters['space_id'], fn (Builder $query, int $id) => $query->where('space_id', $id))
                ->when($filters['user_id'], fn (Builder $query, int $id) => $query->whereHas('checkInGroup', fn (Builder $group) => $group->where('created_by', $id)))
                ->orderBy('check_out_date')->orderBy('id')->get();

            $remaining = $pendingStays->keyBy('id');
            $roomRows = $roomRows->flatMap(function (array $row) use ($remaining, $pendingStays): array {
                $matches = $remaining->filter(function (Stay $stay) use ($row): bool {
                    if ((int) $stay->space_id !== (int) $row['space_id'] || (int) ($stay->space_room_id ?? 0) !== (int) ($row['room_id'] ?? 0)) {
                        return false;
                    }

                    return $row['bed_id'] === null || $stay->room_bed_unit_id === null || (int) $stay->room_bed_unit_id === (int) $row['bed_id'];
                });
                $pendingRows = $matches->map(function (Stay $stay) use ($remaining): array {
                    $remaining->forget($stay->id);
                    $spaceName = $stay->space?->title ?: $stay->space?->name ?: '-';
                    $roomName = $stay->room?->title ?: $stay->room?->name ?: 'Unidad completa';
                    $label = $roomName.($stay->bedUnit ? ' / '.$stay->bedUnit->label : '');
                    $pending = $this->dailyResourceRow($spaceName, $label, 1, $stay, null, null);
                    $pending['status'] = 'pending_checkout';
                    $pending['status_label'] = 'Check-out pendiente';

                    return ['space_id' => $stay->space_id, 'room_id' => $stay->space_room_id, 'bed_id' => $stay->room_bed_unit_id, ...$pending];
                })->values()->all();

                $coveredByPending = $pendingStays->contains(fn (Stay $stay): bool => (int) $stay->space_id === (int) $row['space_id']
                    && (int) ($stay->space_room_id ?? 0) === (int) ($row['room_id'] ?? 0)
                    && ($row['bed_id'] === null || $stay->room_bed_unit_id === null || (int) $stay->room_bed_unit_id === (int) $row['bed_id']));

                return [...$pendingRows, ...($coveredByPending && $row['status'] === 'free' ? [] : [$row])];
            })->values();
        }

        return [
            'date' => $reportDate,
            'rooms' => $roomRows,
            'summary' => [
                'rooms' => $roomRows->count(),
                'free' => $roomRows->where('status', 'free')->count(),
                'occupied' => $roomRows->where('status', 'occupied')->count(),
                'reserved' => $roomRows->where('status', 'reserved')->count(),
                'blocked' => $roomRows->where('status', 'blocked')->count(),
                'people' => (int) $roomRows->sum('people'),
                'breakfasts' => (int) $roomRows->sum('breakfast'),
                'balance' => (float) $roomRows->sum('balance'),
            ],
        ];
    }

    private function dailyRoomRows(SpaceRoom $room, Collection $stays, Collection $reservations, Collection $blocks): array
    {
        $roomStays = $stays->where('space_room_id', $room->id);
        $roomReservations = $reservations->filter(fn (Reservation $reservation): bool => $this->reservationMatchesRoom($reservation, $room));
        $wholeRoom = $roomStays->contains(fn (Stay $stay): bool => $stay->room_bed_unit_id === null)
            || $roomReservations->contains(fn (Reservation $reservation): bool => $reservation->roomItems->contains(fn ($item): bool => (int) $item->space_room_id === (int) $room->id)
                || ((int) $reservation->space_room_id === (int) $room->id && $reservation->bedUnitItems->isEmpty()));
        if (! in_array($room->sale_mode, ['bed_unit', 'flexible'], true) || $room->bedUnits->isEmpty() || $wholeRoom) {
            return [['space_id' => $room->space_id, 'room_id' => $room->id, 'bed_id' => null, ...$this->dailyRoomRow($room, $stays, $reservations, $blocks)]];
        }

        return $room->bedUnits->map(function (RoomBedUnit $bed) use ($room, $roomStays, $reservations, $blocks): array {
            $bedStay = $roomStays->first(fn (Stay $stay): bool => (int) $stay->room_bed_unit_id === (int) $bed->id);
            $reservation = $reservations->first(fn (Reservation $reservation): bool => $reservation->bedUnitItems->contains(fn ($item): bool => (int) $item->room_bed_unit_id === (int) $bed->id));
            $bedItem = $reservation?->bedUnitItems->first(fn ($item): bool => (int) $item->room_bed_unit_id === (int) $bed->id);
            $block = $blocks->first(fn (OccupancyBlock $block): bool => (int) $block->space_room_id === (int) $room->id && ($block->room_bed_unit_id === null || (int) $block->room_bed_unit_id === (int) $bed->id));
            $row = $this->dailyResourceRow($room->space?->title ?: $room->space?->name ?: '-', ($room->title ?: $room->name).' / '.$bed->label, 1, $bedStay, $reservation, $block);
            if (! $bedStay && $bedItem) {
                $row['guest'] = $bedItem->guest_name ?: $reservation->guest_name;
                $row['people'] = 1;
                $row['breakfast'] = $reservation->breakfast_included ? 1 : 0;
                $row['total'] = (float) $bedItem->subtotal_amount;
                $row['advance'] = 0;
                $row['balance'] = 0;
            }
            return ['space_id' => $room->space_id, 'room_id' => $room->id, 'bed_id' => $bed->id, ...$row];
        })->all();
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
        $spaceStays = $stays->filter(fn (Stay $stay): bool => (int) $stay->space_id === (int) $space->id && blank($stay->space_room_id))->values();
        if ($spaceStays->isNotEmpty()) {
            return $this->dailyStayGroupRow(
                $space->title ?: $space->name ?: '-',
                'Unidad completa',
                max(1, (int) ($space->max_capacity ?: 1)),
                $spaceStays,
            );
        }
        $stay = null;
        $reservation = $reservations->first(fn (Reservation $reservation): bool => (int) $reservation->space_id === (int) $space->id && blank($reservation->space_room_id));
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
