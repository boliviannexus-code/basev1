@php
    $money = fn ($value, $currency = 'BOB') => money_format_decimal($value).($currency && $currency !== 'BOB' ? ' '.$currency : '');
    $statusTone = [
        'free' => 'success',
        'occupied' => 'primary',
        'reserved' => 'info',
        'blocked' => 'warning',
        'pending_checkout' => 'danger',
    ];
@endphp

<x-ui.table-card title="Habitaciones del día · Importes en BOB" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Habitacion</th>
                    <th>Estado</th>
                    <th>Huesped / detalle</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th class="text-end">Personas</th>
                    <th class="text-end">Adelanto</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($dailyOccupancy['rooms']->groupBy('space_id') as $hotelRooms)
                    <tr class="table-secondary"><th colspan="9" scope="rowgroup">{{ $hotelRooms->first()['space'] }}</th></tr>
                    @foreach ($hotelRooms as $row)
                    <tr class="{{ $row['status'] === 'free' ? 'table-success' : '' }}">
                        <td class="fw-semibold">{{ $row['room'] }}</td>
                        <td><span class="badge text-bg-{{ $statusTone[$row['status']] ?? 'secondary' }}">{{ $row['status_label'] }}</span></td>
                        <td>{{ $row['guest'] ?: '-' }}</td>
                        <td>{{ $row['check_in']?->format('Y-m-d') ?: '-' }}</td>
                        <td>{{ $row['check_out']?->format('Y-m-d') ?: '-' }}</td>
                        <td class="text-end">{{ $row['people'] }}</td>
                        <td class="text-end">{{ $row['advance'] > 0 ? $money($row['advance'], $row['currency']) : '-' }}</td>
                        <td class="text-end">{{ $row['total'] > 0 ? $money($row['total'], $row['currency']) : '-' }}</td>
                        <td class="text-end fw-semibold">{{ $row['balance'] > 0 ? $money($row['balance'], $row['currency']) : '-' }}</td>
                    </tr>
                    @endforeach
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="9">Sin habitaciones activas para el reporte.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.table-card>
