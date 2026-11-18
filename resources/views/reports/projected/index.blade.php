@extends('layouts.admin')

@section('title', 'Proyectado | Reportes')
@section('page-title', 'Cobros proyectados')
@section('page-subtitle', 'Saldos pendientes de estancias activas y reservas')

@section('content')
    <div class="text-center my-4">
        <h2>REPORTE PROYECTADO {{ $to ? 'DESDE ' : '' }}{{ today()->format('d/m/Y') }}@if ($to) HASTA {{ \Carbon\CarbonImmutable::parse($to)->format('d/m/Y') }}@endif</h2>
    </div>

    <x-ui.table-card title="Filtro">
        <form class="row g-3 align-items-end" method="GET" action="{{ route('reports.projected.index') }}">
            <div class="col-sm-6 col-md-3">
                <label class="form-label" for="projected-to">Fecha final</label>
                <input class="form-control @error('to') is-invalid @enderror" id="projected-to" name="to" type="date" value="{{ old('to', $to) }}">
                @error('to') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-auto d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="ti ti-filter"></i> Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.projected.index') }}">Limpiar</a>
            </div>
        </form>
    </x-ui.table-card>

    <div class="row g-3 mb-3">
        @forelse ($rows->groupBy('currency') as $currency => $currencyRows)
            <div class="col-sm-6 col-lg-3">
                <x-ui.stat-card :label="'Pendiente en '.$currency" :value="money_format_decimal($currencyRows->sum('balance')).' '.$currency" icon="ti ti-report-money" tone="info" />
            </div>
        @empty
            <div class="col-12 text-body-secondary">No hay cobros pendientes.</div>
        @endforelse
        @foreach ($rows->groupBy('type') as $type => $typeRows)
            @foreach ($typeRows->groupBy('currency') as $currency => $currencyRows)
                <div class="col-sm-6 col-lg-3">
                    <x-ui.stat-card :label="$type.' · '.$currencyRows->count().' registros'" :value="money_format_decimal($currencyRows->sum('balance')).' '.$currency" icon="ti ti-chart-bar" tone="info" />
                </div>
            @endforeach
        @endforeach
    </div>

    <x-ui.table-card title="Detalle de cobros proyectados">
        <p class="text-body-secondary small">La fecha es una referencia: salida prevista para estancias y llegada para reservas. No representa una fecha de pago comprometida.</p>
        <div class="table-responsive">
            <table class="table table-vcenter">
                <thead><tr><th>Fecha de referencia</th><th>Tipo</th><th>Huésped</th><th>Referencia</th><th class="text-end">Saldo pendiente</th></tr></thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr><td>{{ $row['date']?->format('d/m/Y') ?? '-' }}</td><td>{{ $row['type'] }}</td><td>{{ $row['guest'] }}</td><td>{{ $row['reference'] }}</td><td class="text-end">{{ money_format_decimal($row['balance']) }} {{ $row['currency'] }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-body-secondary">No hay cobros pendientes para mostrar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endsection
