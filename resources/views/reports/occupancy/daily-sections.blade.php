@php
    $money = fn ($value, $currency = 'BOB') => money_format_decimal($value).' '.$currency;
    $statusTone = [
        'free' => 'success',
        'occupied' => 'primary',
        'reserved' => 'info',
        'blocked' => 'warning',
    ];
@endphp

<div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Habitaciones" :value="$dailyOccupancy['summary']['rooms']" icon="ti ti-door" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Libres" :value="$dailyOccupancy['summary']['free']" icon="ti ti-circle-check" tone="success" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Ocupadas" :value="$dailyOccupancy['summary']['occupied']" icon="ti ti-user-check" tone="primary" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Reservadas" :value="$dailyOccupancy['summary']['reserved']" icon="ti ti-calendar-check" tone="info" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Desayunos hoy" :value="$dailyOccupancy['summary']['breakfasts']" icon="ti ti-coffee" tone="warning" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Saldo" :value="money_format_decimal($dailyOccupancy['summary']['balance'])" icon="ti ti-cash" tone="danger" /></div>
</div>

<x-ui.table-card title="Habitaciones del dia" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Alojamiento</th>
                    <th>Habitacion</th>
                    <th>Estado</th>
                    <th>Huesped / detalle</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th class="text-end">Personas</th>
                    <th>Pago</th>
                    <th class="text-end">Adelanto</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dailyOccupancy['rooms'] as $row)
                    <tr class="{{ $row['status'] === 'free' ? 'table-success' : '' }}">
                        <td>{{ $row['space'] }}</td>
                        <td class="fw-semibold">{{ $row['room'] }}</td>
                        <td><span class="badge text-bg-{{ $statusTone[$row['status']] ?? 'secondary' }}">{{ $row['status_label'] }}</span></td>
                        <td>{{ $row['guest'] ?: '-' }}</td>
                        <td>{{ $row['check_in']?->format('Y-m-d') ?: '-' }}</td>
                        <td>{{ $row['check_out']?->format('Y-m-d') ?: '-' }}</td>
                        <td class="text-end">{{ $row['people'] }}</td>
                        <td>{{ $row['payment_status'] ?: '-' }}</td>
                        <td class="text-end">{{ $row['advance'] > 0 ? $money($row['advance'], $row['currency']) : '-' }}</td>
                        <td class="text-end">{{ $row['total'] > 0 ? $money($row['total'], $row['currency']) : '-' }}</td>
                        <td class="text-end fw-semibold">{{ $row['balance'] > 0 ? $money($row['balance'], $row['currency']) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="11">Sin habitaciones activas para el reporte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.table-card>

<x-ui.table-card title="Desayunos para el dia" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Alojamiento</th><th>Habitacion</th><th>Huesped</th><th class="text-end">Cantidad</th></tr></thead>
            <tbody>
                @forelse ($dailyOccupancy['breakfasts'] as $row)
                    <tr>
                        <td>{{ $row['space'] }}</td>
                        <td>{{ $row['room'] }}</td>
                        <td>{{ $row['guest'] }}</td>
                        <td class="text-end fw-semibold">{{ $row['people'] }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="4">No hay desayunos para esta fecha.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Total desayunos</th>
                    <th class="text-end">{{ $dailyOccupancy['summary']['breakfasts'] }}</th>
                </tr>
            </tfoot>
        </table>
    </div>
    <div class="card-footer text-body-secondary">
        Los desayunos de {{ $dailyOccupancy['date']->format('d/m/Y') }} se calculan con la ocupabilidad de {{ $dailyOccupancy['previousDate']->format('d/m/Y') }}.
    </div>
</x-ui.table-card>
