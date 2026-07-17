@extends('layouts.admin')

@section('title', 'Business Intelligence | '.config('app.name', 'Base Admin'))
@section('page-title', 'Business Intelligence')
@section('page-subtitle', 'Indicadores avanzados de ingresos, ocupacion, forecast, segmentos y canales')

@section('content')
    @php
        $money = fn ($value) => money_format_decimal($value).' BOB';
        $pct = fn ($value) => number_format((float) $value, 2).' %';
        $bar = fn ($value) => min(100, max(0, (float) $value));
    @endphp

    <x-ui.table-card title="Filtros">
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
                <label class="form-label" for="bi-space">Alojamiento</label>
                <select class="form-select" id="bi-space" name="space_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}" @selected((int) $filters['space_id'] === (int) $space->id)>{{ $space->title ?: $space->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary flex-fill" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('business-intelligence.index') }}"><i class="ti ti-eraser"></i></a>
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

        <div class="row g-3 mt-1">
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="ADR" :value="$money($kpis['adr'])" icon="ti ti-bed" tone="primary" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="RevPAR" :value="$money($kpis['revpar'])" icon="ti ti-chart-arrows" tone="success" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="TRevPAR" :value="$money($kpis['trevpar'])" icon="ti ti-report-money" tone="info" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="GOPPAR" :value="$money($kpis['goppar'])" icon="ti ti-businessplan" tone="warning" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ocupacion" :value="$pct($kpis['occupancy_rate'])" icon="ti ti-calendar-stats" tone="success" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingresos hospedaje" :value="$money($kpis['lodging_revenue'])" icon="ti ti-home-dollar" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingresos totales" :value="$money($kpis['total_revenue'])" icon="ti ti-cash-banknote" tone="info" /></div>
            <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="GOP" :value="$money($kpis['gross_operating_profit'])" icon="ti ti-scale" tone="primary" /></div>
        </div>

        <div class="row g-3 mt-3">
            <div class="col-lg-6">
                <x-ui.table-card title="Comparativo por periodo">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr><th>Ingresos hospedaje</th><td class="text-end fw-semibold">{{ $pct($comparison['revenue_delta']) }}</td></tr>
                            <tr><th>Noches ocupadas</th><td class="text-end fw-semibold">{{ $pct($comparison['occupied_delta']) }}</td></tr>
                            <tr><th>Noches disponibles</th><td class="text-end">{{ $kpis['available_room_nights'] }}</td></tr>
                            <tr><th>Noches ocupadas</th><td class="text-end">{{ $kpis['occupied_room_nights'] }}</td></tr>
                            <tr><th>Egresos operativos</th><td class="text-end">{{ $money($kpis['expenses']) }}</td></tr>
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
            <div class="col-lg-6">
                <x-ui.table-card title="Pronostico de ocupacion">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>Fecha</th><th class="text-end">Reservadas</th><th class="text-end">Forecast</th></tr></thead>
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

        <div class="row g-3 mt-3">
            <div class="col-xl-7">
                <x-ui.table-card title="Rentabilidad por canal de venta">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead><tr><th>Canal</th><th class="text-end">Reservas</th><th class="text-end">Noches</th><th class="text-end">Ingresos</th><th class="text-end">Comision</th><th class="text-end">Beneficio</th><th class="text-end">Margen</th></tr></thead>
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
                <x-ui.table-card title="Segmentacion de clientes">
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
