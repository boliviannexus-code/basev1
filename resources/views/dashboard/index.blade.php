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
        $breakfastTomorrowDate = $today->addDay();
        $breakfastBySpace = $breakfast['by_space']->keyBy(fn (array $row) => $row['space']?->id);
        $breakfastTomorrowBySpace = $breakfastTomorrow['by_space']->keyBy(fn (array $row) => $row['space']?->id);
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

    <div id="dashboard-content" data-dashboard-url="{{ route('dashboard') }}">
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
                    <label for="dashboard-date" class="visually-hidden">Fecha de consulta</label>
                    <input id="dashboard-date" type="date" name="date" class="form-control form-control-sm w-auto" aria-label="Fecha de consulta" value="{{ $today->toDateString() }}" max="{{ $currentDate->toDateString() }}" required>
                    @if ($isHistorical)<a href="{{ route('dashboard') }}" data-dashboard-today class="small">Volver a hoy</a>@endif
                    <span data-dashboard-status class="small text-body-secondary" role="status" aria-live="polite"></span>
                    <span class="badge text-bg-info">Sin datos financieros</span>
                    <span class="text-body-secondary small">Desayunos calculados con las estancias registradas de la noche anterior</span>
                </div>
            </div>
        </div>
    </div>

    @if ($isHistorical)
        <div class="alert alert-info" role="status">
            Historico del {{ $today->format('d/m/Y') }}: ocupacion, ingresos, salidas y desayunos reconstruidos con los registros disponibles.
            La capacidad y los datos de las estancias reflejan sus valores guardados actuales; los cambios posteriores pueden afectar el resultado.
            Las alertas y proximas reservas muestran informacion actual.
        </div>
    @endif

    <div class="dashboard-kpi-grid" aria-label="Resumen general de ocupabilidad">
        <x-ui.stat-card label="Ocupacion" :value="$occupancy['occupancy_rate'].'%'" icon="ti ti-bed" tone="primary" />
        <x-ui.stat-card label="Habitaciones ocupadas" :value="$occupancy['occupied_units'].' / '.$occupancy['total_units']" icon="ti ti-door" tone="primary" />
        <x-ui.stat-card :label="$isHistorical ? 'Disponibles en la fecha' : 'Disponibles esta noche'" :value="$occupancy['available_units']" icon="ti ti-circle-check" tone="success" />
        <x-ui.stat-card :label="$isHistorical ? 'Check-ins en la fecha' : 'Check-ins hoy'" :value="$occupancy['check_ins_today']" icon="ti ti-login" tone="info" />
        <x-ui.stat-card :label="$isHistorical ? 'Check-outs en la fecha' : 'Check-outs hoy'" :value="$occupancy['check_outs_today']" icon="ti ti-logout" tone="warning" />
        <x-ui.stat-card :label="$isHistorical ? 'Desayunos ('.$today->format('d/m').')' : 'Desayunos (hoy)'" :value="$breakfast['total_people']" icon="ti ti-coffee" tone="warning" />
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.table-card title="Resumen por espacio">
                <table class="table table-sm table-hover align-middle mb-0 reservation-mobile-table">
                    <thead>
                        <tr>
                            <th>Espacio</th>
                            <th class="text-end">Ocupacion</th>
                            <th class="text-end">Ocupadas</th>
                            <th class="text-end">Disponibles</th>
                            <th class="text-end">Check-ins</th>
                            <th class="text-end">Check-outs</th>
                            <th class="text-end">Huespedes</th>
                            <th class="text-end">{{ $isHistorical ? 'Desayunos (fecha seleccionada)' : 'Desayunos (hoy)' }}</th>
                            <th class="text-end">Desayunos {{ $breakfastTomorrowDate->locale('es')->dayName }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($occupancy['by_space'] as $row)
                            <tr>
                                <td data-label="Espacio" class="fw-semibold">{{ $row['space']?->title ?: $row['space']?->name ?: 'Espacio' }}</td>
                                <td data-label="Ocupación" class="text-end">
                                    <span class="badge bg-{{ $row['occupancy_rate'] >= 80 ? 'danger' : ($row['occupancy_rate'] >= 50 ? 'warning' : 'success') }}-lt">{{ $row['occupancy_rate'] }}%</span>
                                </td>
                                <td data-label="Ocupadas" class="text-end">{{ $row['occupied_units'] }} / {{ $row['total_units'] }}</td>
                                <td data-label="Disponibles" class="text-end fw-semibold text-success">{{ $row['available_units'] }}</td>
                                <td data-label="Check-ins" class="text-end">{{ $row['check_ins_today'] }}</td>
                                <td data-label="Check-outs" class="text-end">{{ $row['check_outs_today'] }}</td>
                                <td data-label="Personas" class="text-end fw-semibold">{{ $row['guests'] }}</td>
                                <td data-label="{{ $isHistorical ? 'Desayunos (fecha seleccionada)' : 'Desayunos (hoy)' }}" class="text-end fw-semibold">{{ $breakfastBySpace->get($row['space']?->id)['people'] ?? 0 }}</td>
                                <td data-label="Desayunos {{ $breakfastTomorrowDate->locale('es')->dayName }}" class="text-end fw-semibold">{{ $breakfastTomorrowBySpace->get($row['space']?->id)['people'] ?? 0 }}</td>
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
            <h3 class="h4 mb-0" id="operational-alerts-title">Alertas operativas @if ($isHistorical)<small class="text-body-secondary">(actuales)</small>@endif</h3>
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
                <div><span>Reservas para hoy</span><strong>{{ $alerts['reservations_today']->count() }}</strong><small>Ingresos confirmados para hoy</small></div>
            </button>
        </div>
    </section>

    @include('dashboard.partials.operational-alert-modals', compact('alerts', 'dashboardCompany', 'stayUnit', 'reservationUnits'))

    @if (! $dashboardCompany)
        <x-ui.table-card title="Empresas" class="mt-3">
            <table class="table table-sm table-hover align-middle mb-0 reservation-mobile-table">
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
                            <td data-label="Empresa" class="fw-semibold">{{ $company->name }}</td>
                            <td data-label="Usuarios" class="text-end">{{ $company->users_count }}</td>
                            <td data-label="Espacios activos" class="text-end">{{ $company->active_spaces_count }}</td>
                            <td data-label="Privados" class="text-end">{{ $company->private_spaces_count }}</td>
                            <td data-label="Compartidos" class="text-end">{{ $company->shared_spaces_count }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-4" colspan="5">Sin empresas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @endif
    </div>
@endsection


@push('scripts')
<script>
(() => {
    let request;

    async function updateDashboard(date) {
        const container = document.getElementById('dashboard-content');
        const input = container.querySelector('#dashboard-date');
        if (!date || !input.checkValidity()) {
            input.reportValidity();
            return;
        }

        request?.abort();
        const activeRequest = new AbortController();
        request = activeRequest;
        const status = container.querySelector('[data-dashboard-status]');
        const url = new URL(container.dataset.dashboardUrl, window.location.origin);
        url.searchParams.set('date', date);
        container.setAttribute('aria-busy', 'true');
        status.textContent = 'Actualizando…';

        try {
            const response = await fetch(url, {
                signal: activeRequest.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'text/html' },
            });
            if (!response.ok || response.redirected) throw new Error('No se pudo actualizar el dashboard. Intenta nuevamente.');
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const updated = page.getElementById('dashboard-content');
            if (!updated) throw new Error('No se pudo actualizar el dashboard. Recarga la pagina.');
            if (request !== activeRequest) return;
            container.replaceWith(updated);
            window.history.replaceState(null, '', url);
            updated.querySelector('#dashboard-date').focus({ preventScroll: true });
        } catch (error) {
            if (error.name !== 'AbortError' && request === activeRequest) {
                status.textContent = error.message;
                status.classList.add('text-danger');
            }
        } finally {
            if (request === activeRequest) {
                document.getElementById('dashboard-content').removeAttribute('aria-busy');
            }
        }
    }

    document.addEventListener('change', (event) => {
        if (event.target.matches('#dashboard-date')) updateDashboard(event.target.value);
    });
    document.addEventListener('click', (event) => {
        const todayLink = event.target.closest('[data-dashboard-today]');
        if (!todayLink) return;
        event.preventDefault();
        const input = document.getElementById('dashboard-date');
        input.value = input.max;
        updateDashboard(input.value);
    });
})();
</script>
@endpush
