<?php

namespace App\Services\Reports;

use App\Models\CashRegisterExpense;
use App\Models\CashRegisterLodgingPayment;
use App\Models\Reservation;
use App\Models\ReservationChannel;
use App\Models\Sale;
use App\Models\Space;
use App\Models\SpaceCashExpense;
use App\Models\SpaceCashIncome;
use App\Models\SpaceCashLodgingPayment;
use App\Models\SpaceCashReservationPayment;
use App\Models\Stay;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BusinessIntelligenceService
{
    public function filters(array $input): array
    {
        $from = CarbonImmutable::parse($input['from'] ?? now()->startOfMonth())->startOfDay();
        $to = CarbonImmutable::parse($input['to'] ?? now())->endOfDay();

        if ($to->lt($from)) {
            [$from, $to] = [$to->startOfDay(), $from->endOfDay()];
        }

        return [
            'from' => $from,
            'to' => $to,
            'space_id' => filled($input['space_id'] ?? null) ? (int) $input['space_id'] : null,
        ];
    }

    public function options(int $companyId): array
    {
        return [
            'spaces' => Space::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderBy('title')
                ->orderBy('name')
                ->get(['id', 'title', 'name']),
        ];
    }

    public function build(int $companyId, array $filters): array
    {
        $spaces = $this->spaces($companyId, $filters);
        $spaceIds = $spaces->pluck('id')->all();
        $availableRoomNights = $this->availableRoomNights($spaces, $filters['from'], $filters['to']);
        $occupiedRoomNights = $this->occupiedRoomNights($companyId, $spaceIds, $filters['from'], $filters['to']);
        $futureWindow = [
            'from' => CarbonImmutable::now()->startOfDay(),
            'to' => CarbonImmutable::now()->startOfDay()->addDays(30),
        ];

        $lodgingRevenue = $this->lodgingRevenue($companyId, $spaceIds, $filters['from'], $filters['to']);
        $reservationRevenue = $this->reservationRevenue($companyId, $spaceIds, $filters['from'], $filters['to']);
        $otherRevenue = $this->otherRevenue($companyId, $filters['from'], $filters['to']);
        $expenses = $this->expenses($companyId, $filters['from'], $filters['to']);
        $totalRevenue = $lodgingRevenue + $reservationRevenue + $otherRevenue;
        $grossOperatingProfit = $totalRevenue - $expenses;

        return [
            'kpis' => [
                'adr' => $occupiedRoomNights > 0 ? $lodgingRevenue / $occupiedRoomNights : 0,
                'revpar' => $availableRoomNights > 0 ? $lodgingRevenue / $availableRoomNights : 0,
                'trevpar' => $availableRoomNights > 0 ? $totalRevenue / $availableRoomNights : 0,
                'goppar' => $availableRoomNights > 0 ? $grossOperatingProfit / $availableRoomNights : 0,
                'occupancy_rate' => $availableRoomNights > 0 ? ($occupiedRoomNights / $availableRoomNights) * 100 : 0,
                'lodging_revenue' => $lodgingRevenue,
                'total_revenue' => $totalRevenue,
                'expenses' => $expenses,
                'gross_operating_profit' => $grossOperatingProfit,
                'available_room_nights' => $availableRoomNights,
                'occupied_room_nights' => $occupiedRoomNights,
            ],
            'comparison' => $this->comparison($companyId, $filters, $spaces),
            'occupancy' => [
                'daily' => $this->occupancySeries($companyId, $spaceIds, $spaces, $filters['from'], $filters['to'], 'day'),
                'weekly' => $this->occupancySeries($companyId, $spaceIds, $spaces, $filters['from'], $filters['to'], 'week'),
                'monthly' => $this->occupancySeries($companyId, $spaceIds, $spaces, $filters['from'], $filters['to'], 'month'),
            ],
            'forecast' => $this->forecast($companyId, $spaceIds, $spaces, $futureWindow['from'], $futureWindow['to']),
            'segments' => $this->segments($companyId, $spaceIds, $filters['from'], $filters['to']),
            'channels' => $this->channelProfitability($companyId, $spaceIds, $filters['from'], $filters['to']),
        ];
    }

    private function spaces(int $companyId, array $filters): Collection
    {
        return Space::query()
            ->withCount(['rooms as active_rooms_count' => fn (Builder $query) => $query->where('status', 'active')])
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->when($filters['space_id'], fn (Builder $query, int $id) => $query->whereKey($id))
            ->get(['id', 'title', 'name', 'max_capacity']);
    }

    private function roomCount(Collection $spaces): int
    {
        return (int) $spaces->sum(fn (Space $space): int => max(1, (int) $space->active_rooms_count));
    }

    private function availableRoomNights(Collection $spaces, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $days = max(1, $from->startOfDay()->diffInDays($to->startOfDay()) + 1);

        return $this->roomCount($spaces) * $days;
    }

    private function occupiedRoomNights(int $companyId, array $spaceIds, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) Stay::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereIn('space_id', $spaceIds))
            ->whereDate('check_in_date', '<=', $to->toDateString())
            ->whereDate('check_out_date', '>', $from->toDateString())
            ->get(['check_in_date', 'check_out_date', 'space_room_id', 'space_id'])
            ->sum(fn (Stay $stay): int => $this->overlapNights($stay->check_in_date, $stay->check_out_date, $from, $to));
    }

    private function lodgingRevenue(int $companyId, array $spaceIds, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $pos = CashRegisterLodgingPayment::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereHas('stay', fn (Builder $stay) => $stay->whereIn('space_id', $spaceIds)))
            ->sum('amount_bob');

        $space = SpaceCashLodgingPayment::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereHas('stay', fn (Builder $stay) => $stay->whereIn('space_id', $spaceIds)))
            ->sum('amount_bob');

        return (float) $pos + (float) $space;
    }

    private function reservationRevenue(int $companyId, array $spaceIds, CarbonImmutable $from, CarbonImmutable $to): float
    {
        return (float) SpaceCashReservationPayment::query()
            ->where('company_id', $companyId)
            ->where('status', 'active')
            ->whereBetween('created_at', [$from, $to])
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereHas('reservationGroup.reservations', fn (Builder $reservation) => $reservation->whereIn('space_id', $spaceIds)))
            ->sum('amount_bob');
    }

    private function otherRevenue(int $companyId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $pos = Sale::query()
            ->whereHas('cashRegister', fn (Builder $query) => $query->where('company_id', $companyId))
            ->where('status', 'completed')
            ->whereBetween('sale_date', [$from, $to])
            ->sum('total');

        $incomes = SpaceCashIncome::query()
            ->where('company_id', $companyId)
            ->whereBetween('received_at', [$from, $to])
            ->sum('amount');

        return (float) $pos + (float) $incomes;
    }

    private function expenses(int $companyId, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $pos = CashRegisterExpense::query()
            ->where('company_id', $companyId)
            ->whereBetween('spent_at', [$from, $to])
            ->sum('amount');

        $space = SpaceCashExpense::query()
            ->where('company_id', $companyId)
            ->whereBetween('spent_at', [$from, $to])
            ->sum('amount');

        return (float) $pos + (float) $space;
    }

    private function occupancySeries(int $companyId, array $spaceIds, Collection $spaces, CarbonImmutable $from, CarbonImmutable $to, string $grain): Collection
    {
        $roomCount = $this->roomCount($spaces);
        $stays = Stay::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['occupied', 'checked_out'])
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereIn('space_id', $spaceIds))
            ->whereDate('check_in_date', '<=', $to->toDateString())
            ->whereDate('check_out_date', '>', $from->toDateString())
            ->get(['check_in_date', 'check_out_date']);

        return collect($this->periodBuckets($from, $to, $grain))
            ->map(function (array $bucket) use ($stays, $roomCount): array {
                $available = $roomCount * $bucket['days'];
                $occupied = (int) $stays->sum(fn (Stay $stay): int => $this->overlapNights($stay->check_in_date, $stay->check_out_date, $bucket['from'], $bucket['to']));

                return [
                    'label' => $bucket['label'],
                    'occupied' => $occupied,
                    'available' => $available,
                    'rate' => $available > 0 ? round(($occupied / $available) * 100, 2) : 0,
                ];
            });
    }

    private function forecast(int $companyId, array $spaceIds, Collection $spaces, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $roomCount = $this->roomCount($spaces);
        $reservations = Reservation::query()
            ->where('company_id', $companyId)
            ->whereIn('status', Reservation::BLOCKING_STATUSES)
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereIn('space_id', $spaceIds))
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>', $from->toDateString())
            ->get(['check_in', 'check_out']);

        return collect(CarbonPeriod::create($from, $to))
            ->map(function ($date) use ($reservations, $roomCount): array {
                $day = CarbonImmutable::parse($date);
                $reserved = (int) $reservations->filter(fn (Reservation $reservation): bool => $reservation->check_in->lte($day) && $reservation->check_out->gt($day))->count();

                return [
                    'label' => $day->format('Y-m-d'),
                    'reserved' => $reserved,
                    'available' => $roomCount,
                    'rate' => $roomCount > 0 ? round(($reserved / $roomCount) * 100, 2) : 0,
                ];
            })
            ->take(31)
            ->values();
    }

    private function segments(int $companyId, array $spaceIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $reservations = Reservation::query()
            ->with('reservationChannel')
            ->where('company_id', $companyId)
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereIn('space_id', $spaceIds))
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>=', $from->toDateString())
            ->get(['id', 'reservation_channel_id', 'guest_country', 'guests', 'total_amount']);

        $byCountry = $reservations
            ->groupBy(fn (Reservation $reservation): string => $reservation->guest_country ?: 'Sin pais')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'bookings' => $rows->count(),
                'guests' => (int) $rows->sum('guests'),
                'revenue' => (float) $rows->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->take(8)
            ->values();

        $byChannel = $reservations
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservationChannel?->name ?: 'Sin canal')
            ->map(fn (Collection $rows, string $name): array => [
                'name' => $name,
                'bookings' => $rows->count(),
                'guests' => (int) $rows->sum('guests'),
                'revenue' => (float) $rows->sum('total_amount'),
            ])
            ->sortByDesc('revenue')
            ->take(8)
            ->values();

        return collect([
            'countries' => $byCountry,
            'channels' => $byChannel,
        ]);
    }

    private function channelProfitability(int $companyId, array $spaceIds, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $channels = ReservationChannel::query()
            ->where('company_id', $companyId)
            ->get(['id', 'name', 'commission_percent']);

        $reservations = Reservation::query()
            ->where('company_id', $companyId)
            ->when($spaceIds !== [], fn (Builder $query) => $query->whereIn('space_id', $spaceIds))
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>=', $from->toDateString())
            ->whereNotIn('status', ['cancelled', 'rejected', 'expired', 'no_show'])
            ->get(['reservation_channel_id', 'total_amount', 'nights']);

        return $reservations
            ->groupBy('reservation_channel_id')
            ->map(function (Collection $rows, $channelId) use ($channels): array {
                $channel = $channels->firstWhere('id', $channelId);
                $revenue = (float) $rows->sum('total_amount');
                $commissionRate = (float) ($channel?->commission_percent ?? 0);
                $commission = round($revenue * ($commissionRate / 100), 2);

                return [
                    'name' => $channel?->name ?: 'Sin canal',
                    'bookings' => $rows->count(),
                    'nights' => (int) $rows->sum('nights'),
                    'revenue' => $revenue,
                    'commission' => $commission,
                    'profit' => $revenue - $commission,
                    'margin' => $revenue > 0 ? (($revenue - $commission) / $revenue) * 100 : 0,
                ];
            })
            ->sortByDesc('profit')
            ->values();
    }

    private function comparison(int $companyId, array $filters, Collection $spaces): array
    {
        $days = max(1, $filters['from']->startOfDay()->diffInDays($filters['to']->startOfDay()) + 1);
        $previousTo = $filters['from']->subDay()->endOfDay();
        $previousFrom = $previousTo->subDays($days - 1)->startOfDay();
        $previousFilters = [
            ...$filters,
            'from' => $previousFrom,
            'to' => $previousTo,
        ];
        $spaceIds = $spaces->pluck('id')->all();

        $currentRevenue = $this->lodgingRevenue($companyId, $spaceIds, $filters['from'], $filters['to']);
        $previousRevenue = $this->lodgingRevenue($companyId, $spaceIds, $previousFilters['from'], $previousFilters['to']);
        $currentOccupied = $this->occupiedRoomNights($companyId, $spaceIds, $filters['from'], $filters['to']);
        $previousOccupied = $this->occupiedRoomNights($companyId, $spaceIds, $previousFilters['from'], $previousFilters['to']);

        return [
            'period' => $previousFrom->format('Y-m-d').' al '.$previousTo->format('Y-m-d'),
            'revenue_delta' => $this->delta($currentRevenue, $previousRevenue),
            'occupied_delta' => $this->delta($currentOccupied, $previousOccupied),
        ];
    }

    private function delta(float|int $current, float|int $previous): float
    {
        return $previous > 0 ? (($current - $previous) / $previous) * 100 : ($current > 0 ? 100 : 0);
    }

    private function periodBuckets(CarbonImmutable $from, CarbonImmutable $to, string $grain): array
    {
        $buckets = [];
        $cursor = match ($grain) {
            'week' => $from->startOfWeek(),
            'month' => $from->startOfMonth(),
            default => $from->startOfDay(),
        };

        while ($cursor->lte($to)) {
            $end = match ($grain) {
                'week' => $cursor->endOfWeek(),
                'month' => $cursor->endOfMonth(),
                default => $cursor->endOfDay(),
            };
            $bucketFrom = $cursor->lt($from) ? $from : $cursor;
            $bucketTo = $end->gt($to) ? $to : $end;

            $buckets[] = [
                'from' => $bucketFrom,
                'to' => $bucketTo,
                'days' => max(1, $bucketFrom->startOfDay()->diffInDays($bucketTo->startOfDay()) + 1),
                'label' => match ($grain) {
                    'week' => 'Sem '.$cursor->format('W/Y'),
                    'month' => $cursor->format('Y-m'),
                    default => $cursor->format('Y-m-d'),
                },
            ];

            $cursor = match ($grain) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $buckets;
    }

    private function overlapNights($start, $end, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $startDate = CarbonImmutable::parse($start)->max($from->startOfDay());
        $endDate = CarbonImmutable::parse($end)->min($to->copy()->addDay()->startOfDay());

        return max(0, (int) $startDate->diffInDays($endDate));
    }
}
