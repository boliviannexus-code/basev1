<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Reservation;
use App\Models\ReservationGroup;
use App\Models\Stay;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('dashboard.view');

        $user = auth()->user();
        $company = CompanyContext::activeCompany($user);
        $companyId = CompanyContext::id($user);
        $today = CarbonImmutable::today();

        return view('dashboard.index', [
            'dashboardCompany' => $company,
            'dashboardCompanies' => $this->companySummaries($companyId),
            'today' => $today,
            'yesterday' => $today->subDay(),
            'occupancy' => $this->occupancySummary($companyId, $today),
            'breakfast' => $this->breakfastSummary($companyId, $today),
            'reservations' => $this->reservationSummary($companyId, $today),
        ]);
    }

    private function companySummaries(?int $companyId): Collection
    {
        return Company::query()
            ->when($companyId, fn (Builder $query): Builder => $query->whereKey($companyId))
            ->withCount([
                'users',
                'spaces as active_spaces_count' => fn (Builder $query): Builder => $query->where('status', 'active'),
                'spaces as shared_spaces_count' => fn (Builder $query): Builder => $query->whereHas('spaceMode', fn (Builder $mode): Builder => $mode->where('slug', 'compartido')),
                'spaces as private_spaces_count' => fn (Builder $query): Builder => $query->whereHas('spaceMode', fn (Builder $mode): Builder => $mode->where('slug', 'privado')),
            ])
            ->orderBy('name')
            ->get();
    }

    private function occupancySummary(?int $companyId, CarbonImmutable $today): array
    {
        $occupiedStays = $this->staysOnDate($companyId, $today)
            ->with(['space.spaceMode', 'room'])
            ->get();

        $sharedStays = $occupiedStays->filter(fn (Stay $stay): bool => $stay->space?->spaceMode?->slug === 'compartido');
        $privateStays = $occupiedStays->filter(fn (Stay $stay): bool => $stay->space?->spaceMode?->slug === 'privado');

        return [
            'shared_rooms' => $sharedStays
                ->filter(fn (Stay $stay): bool => $stay->space_room_id !== null)
                ->unique(fn (Stay $stay): string => $stay->space_id.'-'.$stay->space_room_id)
                ->count(),
            'shared_guests' => (int) $sharedStays->sum('people_count'),
            'private_spaces' => $privateStays
                ->unique('space_id')
                ->count(),
            'private_guests' => (int) $privateStays->sum('people_count'),
            'total_stays' => $occupiedStays->count(),
            'total_guests' => (int) $occupiedStays->sum('people_count'),
            'by_space' => $occupiedStays
                ->groupBy('space_id')
                ->map(function (Collection $stays): array {
                    $space = $stays->first()->space;

                    return [
                        'space' => $space,
                        'mode' => $space?->spaceMode?->slug,
                        'occupied_units' => $stays
                            ->unique(fn (Stay $stay): string => $stay->space_id.'-'.($stay->space_room_id ?: 'space').'-'.($stay->room_bed_unit_id ?: 'room'))
                            ->count(),
                        'guests' => (int) $stays->sum('people_count'),
                    ];
                })
                ->sortBy(fn (array $row): string => (string) ($row['space']?->title ?: $row['space']?->name))
                ->values(),
        ];
    }

    private function breakfastSummary(?int $companyId, CarbonImmutable $today): array
    {
        $serviceDate = $today;
        $occupancyDate = $today->subDay();
        $stays = $this->staysOnDate($companyId, $occupancyDate, ['occupied', 'checked_out'])
            ->where('breakfast_included', true)
            ->with(['space.spaceMode'])
            ->get();

        $bySpace = $stays
            ->groupBy('space_id')
            ->map(function (Collection $spaceStays) use ($serviceDate, $occupancyDate): array {
                $space = $spaceStays->first()->space;

                return [
                    'space' => $space,
                    'mode' => $space?->spaceMode?->slug,
                    'service_date' => $serviceDate,
                    'occupancy_date' => $occupancyDate,
                    'people' => (int) $spaceStays->sum('people_count'),
                    'stays' => $spaceStays->count(),
                ];
            })
            ->sortBy(fn (array $row): string => (string) ($row['space']?->title ?: $row['space']?->name))
            ->values();

        return [
            'service_date' => $serviceDate,
            'occupancy_date' => $occupancyDate,
            'total_people' => (int) $stays->sum('people_count'),
            'shared_people' => (int) $stays->filter(fn (Stay $stay): bool => $stay->space?->spaceMode?->slug === 'compartido')->sum('people_count'),
            'private_people' => (int) $stays->filter(fn (Stay $stay): bool => $stay->space?->spaceMode?->slug === 'privado')->sum('people_count'),
            'by_space' => $bySpace,
        ];
    }

    private function reservationSummary(?int $companyId, CarbonImmutable $today): array
    {
        $statuses = [
            'pending_payment' => 'Pendientes de pago',
            'payment_under_review' => 'En revision',
            'confirmed' => 'Confirmadas',
            'checked_in' => 'En check-in',
            'cancelled' => 'Canceladas',
            'expired' => 'Vencidas',
            'no_show' => 'No show',
        ];

        $groupCounts = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $singleCounts = Reservation::query()
            ->withoutGlobalScope('company')
            ->whereNull('reservation_group_id')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $counts = collect($statuses)
            ->map(fn (string $label, string $status): array => [
                'status' => $status,
                'label' => $label,
                'total' => (int) ($groupCounts[$status] ?? 0) + (int) ($singleCounts[$status] ?? 0),
            ])
            ->values();

        return [
            'status_counts' => $counts,
            'arrivals_today' => $this->reservationArrivals($companyId, $today, $today),
            'arrivals_tomorrow' => $this->reservationArrivals($companyId, $today->addDay(), $today->addDay()),
            'upcoming' => $this->upcomingReservations($companyId, $today),
        ];
    }

    private function reservationArrivals(?int $companyId, CarbonImmutable $from, CarbonImmutable $to): int
    {
        $statuses = ['pending_payment', 'payment_under_review', 'confirmed', 'checked_in'];

        $groupArrivals = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->whereIn('status', $statuses)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->count();

        $singleArrivals = Reservation::query()
            ->withoutGlobalScope('company')
            ->whereNull('reservation_group_id')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->whereIn('status', $statuses)
            ->whereBetween('check_in', [$from->toDateString(), $to->toDateString()])
            ->count();

        return $groupArrivals + $singleArrivals;
    }

    private function upcomingReservations(?int $companyId, CarbonImmutable $today): Collection
    {
        $statuses = ['pending_payment', 'payment_under_review', 'confirmed', 'checked_in'];
        $until = $today->addDays(7)->toDateString();

        $groups = ReservationGroup::query()
            ->withoutGlobalScope('company')
            ->with(['company', 'reservationChannel'])
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->whereIn('status', $statuses)
            ->whereBetween('check_in', [$today->toDateString(), $until])
            ->orderBy('check_in')
            ->limit(8)
            ->get()
            ->map(fn (ReservationGroup $group): array => [
                'code' => $group->code,
                'company' => $group->company?->name,
                'guest' => $group->guest_name,
                'status' => $group->status,
                'channel' => $group->reservationChannel?->name,
                'check_in' => $group->check_in,
                'check_out' => $group->check_out,
                'guests' => (int) $group->guests,
                'type' => 'Grupo',
            ]);

        $singles = Reservation::query()
            ->withoutGlobalScope('company')
            ->with(['company', 'reservationChannel'])
            ->whereNull('reservation_group_id')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->whereIn('status', $statuses)
            ->whereBetween('check_in', [$today->toDateString(), $until])
            ->orderBy('check_in')
            ->limit(8)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'code' => $reservation->code,
                'company' => $reservation->company?->name,
                'guest' => $reservation->guest_name,
                'status' => $reservation->status,
                'channel' => $reservation->reservationChannel?->name,
                'check_in' => $reservation->check_in,
                'check_out' => $reservation->check_out,
                'guests' => (int) $reservation->guests,
                'type' => 'Individual',
            ]);

        return $groups
            ->concat($singles)
            ->sortBy(fn (array $row): string => $row['check_in']?->toDateString().$row['code'])
            ->take(8)
            ->values();
    }

    private function staysOnDate(?int $companyId, CarbonImmutable $date, array $statuses = ['occupied']): Builder
    {
        return Stay::query()
            ->withoutGlobalScope('company')
            ->when($companyId, fn (Builder $query): Builder => $query->where('company_id', $companyId))
            ->whereIn('status', $statuses)
            ->whereDate('check_in_date', '<=', $date->toDateString())
            ->whereDate('check_out_date', '>', $date->toDateString());
    }
}
