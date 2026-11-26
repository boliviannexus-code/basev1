@extends('layouts.admin')

@section('title', 'Análisis del negocio | '.config('app.name', 'Base Admin'))
@section('page-title', 'Análisis del negocio')
@section('page-subtitle', 'Consulta tus cobros, ocupación y reservas por periodo')

@section('content')
    @php
        $money = fn ($value) => money_format_decimal($value).' '.company_currency_code();
        $pct = fn ($value) => number_format((float) $value, 2).' %';
        $bar = fn ($value) => min(100, max(0, (float) $value));
    @endphp

    <x-ui.table-card title="Elige el periodo y el espacio">
        <form class="row g-3 align-items-end" method="GET" action="{{ route('business-intelligence.index') }}" autocomplete="off" data-report-filter-form>
            <div class="col-md-3">
                <label class="form-label" for="bi-from">Desde</label>
                <input class="form-control" id="bi-from" name="from" type="date" value="{{ $filters['from']->toDateString() }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="bi-to">Hasta</label>
                <input class="form-control" id="bi-to" name="to" type="date" value="{{ $filters['to']->toDateString() }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="bi-space">Espacio</label>
                <select class="form-select" id="bi-space" name="space_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" @selected((int) $filters['space_id'] === (int) $space->id)>{{ $space->title ?: $space->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('business-intelligence.index') }}"><i class="ti ti-eraser"></i> Limpiar</a>
            </div>
        </form>
    </x-ui.table-card>

    <div data-report-results>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div>
                <h2 class="h3 mb-1">{{ $company?->name }}</h2>
                <div class="text-body-secondary">{{ $filters['from']->format('Y-m-d') }} al {{ $filters['to']->format('Y-m-d') }}</div>
            </div>
            <div class="text-body-secondary">Comparativo: {{ $comparison['period'] }}</div>
        </div>

        <div class="alert alert-info mt-3" role="note">
            Los cobros se muestran según la fecha en que fueron registrados. Las noches ocupadas se calculan con las fechas de las estancias.
            @if ($filters['space_id']) Las ventas, ingresos adicionales y gastos corresponden a toda la empresa, incluso al elegir un espacio. @endif
        </div>
        <h3 class="h4 mt-4">¿Cómo fue el periodo?</h3>
        <div class="row g-3 mt-1">
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ocupación" :value="$pct($kpis['occupancy_rate'])" icon="ti ti-calendar-stats" tone="success" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Cobros por estancias" :value="$money($kpis['lodging_revenue'])" icon="ti ti-home-dollar" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingresos registrados" :value="$money($kpis['total_revenue'])" icon="ti ti-cash-banknote" tone="info" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingresos menos gastos registrados" :value="$money($kpis['gross_operating_profit'])" icon="ti ti-scale" tone="primary" /></div>
        </div>
        <p class="small text-body-secondary mt-2">Ingresos registrados incluye cobros por estancias y adelantos de reservas, ventas e ingresos adicionales. El resultado resta los gastos registrados y no representa necesariamente la utilidad contable.</p>

        <details class="card mt-3">
            <summary class="card-header fw-semibold" style="cursor:pointer">Ver promedios por habitación y cómo se calculan</summary>
            <div class="card-body">
                <p class="small text-body-secondary">Una noche disponible equivale a una habitación disponible durante una noche. Los promedios dividen cobros o resultados registrados entre las noches del periodo; no son tarifas contratadas.</p>
                <div class="row g-3">
                    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Cobro por noche ocupada" :value="$money($kpis['adr'])" icon="ti ti-bed" tone="primary" /><p class="small mt-2">Cobros por estancias ÷ noches ocupadas.</p></div>
                    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Cobro por noche disponible" :value="$money($kpis['revpar'])" icon="ti ti-chart-arrows" tone="success" /><p class="small mt-2">Cobros por estancias ÷ noches disponibles.</p></div>
                    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingreso por noche disponible" :value="$money($kpis['trevpar'])" icon="ti ti-report-money" tone="info" /><p class="small mt-2">Ingresos registrados ÷ noches disponibles.</p></div>
                    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Resultado por noche disponible" :value="$money($kpis['goppar'])" icon="ti ti-businessplan" tone="warning" /><p class="small mt-2">Ingresos menos gastos registrados ÷ noches disponibles.</p></div>
                </div>
            </div>
        </details>
        <h3 class="h4 mt-4">Comparación y reservas futuras</h3>
        <div class="row g-3 mt-3">
            <div class="col-lg-6">
                <x-ui.table-card title="Comparación con el periodo anterior">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr><th>Variación de cobros por estancias</th><td class="text-end fw-semibold">{{ $comparison['revenue_delta'] === null ? 'Sin base para comparar' : $pct($comparison['revenue_delta']) }}</td></tr>
                            <tr><th>Variación de noches ocupadas</th><td class="text-end fw-semibold">{{ $comparison['occupied_delta'] === null ? 'Sin base para comparar' : $pct($comparison['occupied_delta']) }}</td></tr>
                            <tr><th>Noches disponibles</th><td class="text-end">{{ $kpis['available_room_nights'] }}</td></tr>
                            <tr><th>Noches ocupadas</th><td class="text-end">{{ $kpis['occupied_room_nights'] }}</td></tr>
                            <tr><th>Egresos operativos</th><td class="text-end">{{ $money($kpis['expenses']) }}</td></tr>
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
            <div class="col-lg-6">
                <x-ui.table-card title="Reservas para los próximos días">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Fecha</th><th class="text-end">Reservadas</th><th class="text-end">Porcentaje reservado</th></tr></thead>
                            <tbody>
                                @foreach ($forecast->take(10) as $row)
                                    <tr>
                                        <td>{{ $row['label'] }}</td>
                                        <td class="text-end">{{ $row['reserved'] }}/{{ $row['available'] }}</td>
                                        <td style="min-width: 12rem;">
                                            <div class="progress" style="height: .65rem;">
                                                <div class="progress-bar" style="width: {{ $bar($row['rate']) }}%"></div>
                                            </div>
                                            <div class="small text-body-secondary text-end">{{ $pct($row['rate']) }}</div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-ui.table-card>
            </div>
        </div>

        <p class="small text-body-secondary mt-2">Las reservas futuras muestran hasta 10 días desde hoy y cuentan reservas activas, no estancias actuales. No son un pronóstico de ventas.</p>
        <h3 class="h4 mt-4">Ocupación en el periodo</h3>
        <p class="small text-body-secondary">Noches de estancias frente a la capacidad calculada con los espacios y habitaciones activos, agrupadas por día, semana y mes. Las estancias compartidas se cuentan por separado y pueden superar la capacidad de habitaciones.</p>
        <div class="row g-3 mt-3">
            <div class="col-xl-4">
                <x-ui.table-card title="Ocupacion diaria">
                    @include('business-intelligence.partials.occupancy-table', ['rows' => $occupancy['daily']->take(12), 'pct' => $pct, 'bar' => $bar])
                </x-ui.table-card>
            </div>
            <div class="col-xl-4">
                <x-ui.table-card title="Ocupacion semanal">
                    @include('business-intelligence.partials.occupancy-table', ['rows' => $occupancy['weekly'], 'pct' => $pct, 'bar' => $bar])
                </x-ui.table-card>
            </div>
            <div class="col-xl-4">
                <x-ui.table-card title="Ocupacion mensual">
                    @include('business-intelligence.partials.occupancy-table', ['rows' => $occupancy['monthly'], 'pct' => $pct, 'bar' => $bar])
                </x-ui.table-card>
            </div>
        </div>

        <h3 class="h4 mt-4">Reservas por procedencia</h3>
        <p class="small text-body-secondary">La comisión se estima con el porcentaje actual configurado para cada canal; el monto restante no descuenta otros gastos. El desglose por país y canal incluye todos los estados de reserva.</p>
        <p class="small text-body-secondary">Los importes de estas tablas son montos de reservas, no cobros. Se usan los valores guardados de las reservas que coinciden con el periodo; pueden incluir noches fuera de él.</p>
        <div class="row g-3 mt-3">
            <div class="col-xl-7">
                <x-ui.table-card title="Reservas por canal">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Canal</th><th class="text-end">Reservas</th><th class="text-end">Noches</th><th class="text-end">Monto reservado</th><th class="text-end">Comisión estimada</th><th class="text-end">Monto menos comisión</th><th class="text-end">Porcentaje restante</th></tr></thead>
                            <tbody>
                                @forelse ($channels as $row)
                                    <tr>
                                        <td class="fw-semibold">{{ $row['name'] }}</td>
                                        <td class="text-end">{{ $row['bookings'] }}</td>
                                        <td class="text-end">{{ $row['nights'] }}</td>
                                        <td class="text-end">{{ $money($row['revenue']) }}</td>
                                        <td class="text-end">{{ $money($row['commission']) }}</td>
                                        <td class="text-end fw-semibold">{{ $money($row['profit']) }}</td>
                                        <td class="text-end">{{ $pct($row['margin']) }}</td>
                                    </tr>
                                @empty
                                    <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin reservas por canal en el periodo.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.table-card>
            </div>
            <div class="col-xl-5">
                <x-ui.table-card title="Reservas por país y canal">
                    <div class="row g-3">
                        <div class="col-md-6 col-xl-12">
                            <h3 class="h6">Por pais</h3>
                            @include('business-intelligence.partials.segment-table', ['rows' => $segments['countries'], 'money' => $money])
                        </div>
                        <div class="col-md-6 col-xl-12">
                            <h3 class="h6">Por canal</h3>
                            @include('business-intelligence.partials.segment-table', ['rows' => $segments['channels'], 'money' => $money])
                        </div>
                    </div>
                </x-ui.table-card>
            </div>
        </div>
    </div>
@endsection
