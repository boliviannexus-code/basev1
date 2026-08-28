@extends('layouts.admin')

@section('title', 'Dashboard | '.config('app.name', 'Base Admin'))
@section('page-title', 'Dashboard')
@section('page-subtitle', $dashboardCompany ? 'Ocupabilidad de '.$dashboardCompany->name : 'Ocupabilidad por empresa')

@section('content')
    @php
        $statusTones = [
            'pending_payment' => 'warning',
            'payment_under_review' => 'info',
            'confirmed' => 'success',
            'checked_in' => 'primary',
            'cancelled' => 'secondary',
            'expired' => 'secondary',
        ];
        $modeLabel = fn (?string $mode): string => $mode === 'compartido' ? 'Compartido' : ($mode === 'privado' ? 'Privado' : 'Sin modalidad');
        $breakfastBySpace = $breakfast['by_space']->keyBy(fn (array $row) => $row['space']?->id);
        $stayUnit = fn ($stay): string => collect([
            $stay->space?->title ?: $stay->space?->name,
            $stay->room?->name ?: $stay->room?->title,
            $stay->bedUnit?->label,
        ])->filter()->join(' · ') ?: 'Sin habitacion asignada';
        $reservationUnits = function ($item): string {
            $reservations = $item instanceof \App\Models\ReservationGroup ? $item->reservations : collect([$item]);

            return $reservations->flatMap(function ($reservation): array {
                $units = collect([$reservation->room?->name ?: $reservation->room?->title])
                    ->merge($reservation->roomItems->map(fn ($roomItem) => $roomItem->room?->name ?: $roomItem->room?->title))
                    ->merge($reservation->bedUnitItems->map(fn ($bedItem) => collect([
                        $bedItem->bedUnit?->room?->name ?: $bedItem->bedUnit?->room?->title,
                        $bedItem->bedUnit?->label,
                    ])->filter()->join(' · ')))
                    ->filter();

                return $units->isNotEmpty() ? $units->all() : [$reservation->space?->title ?: $reservation->space?->name];
            })->filter()->unique()->join(', ') ?: 'Sin habitacion asignada';
        };
    @endphp

    <div class="dashboard-hero mb-3">
        <div class="dashboard-company">
            @if ($dashboardCompany?->logo_url)
                <img class="dashboard-company-logo" src="{{ $dashboardCompany->logo_url }}" alt="{{ $dashboardCompany->name }}">
            @else
                <span class="dashboard-company-mark">{{ str($dashboardCompany?->name ?? 'Empresas')->substr(0, 2)->upper() }}</span>
            @endif
            <div>
                <div class="text-body-secondary small">{{ $dashboardCompany ? 'Contexto de empresa' : 'Contexto global' }}</div>
                <h2 class="mb-1">{{ $dashboardCompany?->name ?? 'Todas las empresas' }}</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge text-bg-primary">{{ $today->format('Y-m-d') }}</span>
                    <span class="badge text-bg-info">Sin datos financieros</span>
                    <span class="text-body-secondary small">Desayuno calculado con ocupacion del {{ $yesterday->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-kpi-grid" aria-label="Resumen general de ocupabilidad">
        <x-ui.stat-card label="Ocupacion" :value="$occupancy['occupancy_rate'].'%'" icon="ti ti-bed" tone="primary" />
        <x-ui.stat-card label="Habitaciones ocupadas" :value="$occupancy['occupied_units'].' / '.$occupancy['total_units']" icon="ti ti-door" tone="primary" />
        <x-ui.stat-card label="Disponibles esta noche" :value="$occupancy['available_units']" icon="ti ti-circle-check" tone="success" />
        <x-ui.stat-card label="Check-ins hoy" :value="$occupancy['check_ins_today']" icon="ti ti-login" tone="info" />
        <x-ui.stat-card label="Check-outs hoy" :value="$occupancy['check_outs_today']" icon="ti ti-logout" tone="warning" />
        <x-ui.stat-card label="Desayunos hoy" :value="$breakfast['total_people']" icon="ti ti-coffee" tone="warning" />
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.table-card title="Resumen por espacio">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Espacio</th>
                            <th>Modalidad</th>
                            <th class="text-end">Ocupacion</th>
                            <th class="text-end">Ocupadas</th>
                            <th class="text-end">Disponibles</th>
                            <th class="text-end">Check-ins</th>
                            <th class="text-end">Check-outs</th>
                            <th class="text-end">Huespedes</th>
                            <th class="text-end">Desayunos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($occupancy['by_space'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['space']?->title ?: $row['space']?->name ?: 'Espacio' }}</td>
                                <td><span class="badge text-bg-secondary">{{ $modeLabel($row['mode']) }}</span></td>
                                <td class="text-end">
                                    <span class="badge bg-{{ $row['occupancy_rate'] >= 80 ? 'danger' : ($row['occupancy_rate'] >= 50 ? 'warning' : 'success') }}-lt">{{ $row['occupancy_rate'] }}%</span>
                                </td>
                                <td class="text-end">{{ $row['occupied_units'] }} / {{ $row['total_units'] }}</td>
                                <td class="text-end fw-semibold text-success">{{ $row['available_units'] }}</td>
                                <td class="text-end">{{ $row['check_ins_today'] }}</td>
                                <td class="text-end">{{ $row['check_outs_today'] }}</td>
                                <td class="text-end fw-semibold">{{ $row['guests'] }}</td>
                                <td class="text-end fw-semibold">{{ $breakfastBySpace->get($row['space']?->id)['people'] ?? 0 }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4" colspan="9">No hay espacios activos para mostrar.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>

    <section class="mt-4" aria-labelledby="operational-alerts-title">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
            <h3 class="h4 mb-0" id="operational-alerts-title">Alertas operativas</h3>
            <span class="text-body-secondary small">Prioridades para hoy</span>
        </div>
        <div class="dashboard-alert-grid">
            <button class="dashboard-alert-card dashboard-alert-card-danger" type="button" data-bs-toggle="modal" data-bs-target="#dashboardCheckOutModal">
                <span class="dashboard-alert-icon" aria-hidden="true"><i class="ti ti-door-exit"></i></span>
                <div><span>Habitaciones para check-out</span><strong>{{ $alerts['check_out_rooms']->count() }}</strong><small>Salidas programadas para hoy</small></div>
            </button>
            <button class="dashboard-alert-card dashboard-alert-card-warning" type="button" data-bs-toggle="modal" data-bs-target="#dashboardBalanceModal">
                <span class="dashboard-alert-icon" aria-hidden="true"><i class="ti ti-cash-banknote-off"></i></span>
                <div><span>Habitaciones con saldo pendiente</span><strong>{{ $alerts['pending_balance_rooms']->count() }}</strong><small>Estancias activas por cobrar</small></div>
            </button>
            <button class="dashboard-alert-card dashboard-alert-card-info" type="button" data-bs-toggle="modal" data-bs-target="#dashboardReservationsModal">
                <span class="dashboard-alert-icon" aria-hidden="true"><i class="ti ti-calendar-event"></i></span>
                <div><span>Reservas para hoy</span><strong>{{ $alerts['reservations_today']->count() }}</strong><small>{{ $alerts['pending_reservations'] }} pendientes de pago o revision</small></div>
            </button>
        </div>
    </section>

    @include('dashboard.partials.operational-alert-modals', compact('alerts', 'dashboardCompany', 'stayUnit', 'reservationUnits'))

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.table-card title="Proximas reservas">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @if (! $dashboardCompany)<th>Empresa</th>@endif
                            <th>Codigo</th>
                            <th>Huesped</th>
                            <th>Ingreso</th>
                            <th>Salida</th>
                            <th class="text-end">Personas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations['upcoming'] as $row)
                            <tr>
                                @if (! $dashboardCompany)<td>{{ $row['company'] ?: '-' }}</td>@endif
                                <td>
                                    <div class="fw-semibold">{{ $row['code'] }}</div>
                                    <div class="text-body-secondary small">{{ $row['type'] }} · {{ str($row['status'])->replace('_', ' ') }}</div>
                                </td>
                                <td>{{ $row['guest'] ?: 'Sin huesped' }}</td>
                                <td>{{ $row['check_in']?->format('Y-m-d') }}</td>
                                <td>{{ $row['check_out']?->format('Y-m-d') }}</td>
                                <td class="text-end fw-semibold">{{ $row['guests'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4" colspan="{{ $dashboardCompany ? 5 : 6 }}">Sin reservas proximas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>

    @if (! $dashboardCompany)
        <x-ui.table-card title="Empresas" class="mt-3">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th class="text-end">Usuarios</th>
                        <th class="text-end">Espacios activos</th>
                        <th class="text-end">Privados</th>
                        <th class="text-end">Compartidos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dashboardCompanies as $company)
                        <tr>
                            <td class="fw-semibold">{{ $company->name }}</td>
                            <td class="text-end">{{ $company->users_count }}</td>
                            <td class="text-end">{{ $company->active_spaces_count }}</td>
                            <td class="text-end">{{ $company->private_spaces_count }}</td>
                            <td class="text-end">{{ $company->shared_spaces_count }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-4" colspan="5">Sin empresas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @endif
@endsection
