@php
    $font = 'font-family: dejavusans, sans-serif; font-size: 7px;';
    $th = 'border: 1px solid #aab9c7; background-color: #e3edf5; color: #18364d; font-weight: bold; padding: 2px;';
    $td = 'border: 1px solid #777; padding: 2px;';
    $states = [
        'reserved' => ['R', '#dceeff'],
        'occupied' => ['O', '#e2eaff'],
        'free' => ['L', '#e4f5e8'],
        'checked_out' => ['CO', '#e9ecef'],
        'pending_checkout' => ['CP', '#ffe5d9'],
        'blocked' => ['B', '#fff1cf'],
    ];
    $money = fn ($value, $currency = 'BOB') => number_format((float) $value, 2, '.', ',').($currency && $currency !== 'BOB' ? ' '.$currency : '');
@endphp

<table cellpadding="4" cellspacing="0" style="{{ $font }} border-bottom: 1px solid #444;">
    <tr>
        <td width="18%">
            @if ($logoPath)
                <img src="{{ $logoPath }}" width="62">
            @else
                <span style="font-size: 16px; font-weight: bold;">{{ str($company?->name ?? 'Reporte')->substr(0, 2)->upper() }}</span>
            @endif
        </td>
        <td width="45%">
            <strong style="font-size: 12px;">{{ $company?->name ?? '-' }}</strong><br>
            {{ $company?->legal_name ?: '' }}<br>
            {{ $company?->tax_id ? 'NIT/Doc.: '.$company->tax_id : '' }}<br>
            {{ $company?->address ?: '' }}
        </td>
        <td width="37%" align="right">
            <strong style="font-size: 13px;">{{ $reportTitle }}</strong><br>
            <strong>Fecha impresion:</strong> {{ now()->format('Y-m-d H:i') }}<br>
            <strong>Usuario:</strong> {{ $printedBy?->name ?? '-' }}<br>
            <strong>Email:</strong> {{ $printedBy?->email ?? '-' }}
        </td>
    </tr>
</table>

<h1 style="{{ $font }} font-size: 18px; text-align: center;">{{ $dailyOccupancy['date']->format('d/m/Y') }}</h1>

<h2 style="{{ $font }} font-size: 12px;">Habitaciones del día · Importes en {{ company_currency_code($company) }}</h2>
<table cellpadding="2" cellspacing="0" style="{{ $font }}">
    <tr>
        <th width="12%" style="{{ $th }}">Habitacion</th>
        <th width="6%" style="{{ $th }}">Estado</th>
        <th width="32%" style="{{ $th }}">Huesped / detalle</th>
        <th width="9%" style="{{ $th }}">Check-in</th>
        <th width="9%" style="{{ $th }}">Check-out</th>
        <th width="5%" style="{{ $th }}" align="right">Pers.</th>
        <th width="9%" style="{{ $th }}" align="right">Adelanto</th>
        <th width="9%" style="{{ $th }}" align="right">Total</th>
        <th width="9%" style="{{ $th }}" align="right">Saldo</th>
    </tr>
    @forelse ($dailyOccupancy['rooms']->groupBy('space_id') as $hotelRooms)
        <tr><td style="{{ $th }}" colspan="9">{{ $hotelRooms->first()['space'] }}</td></tr>
        @foreach ($hotelRooms as $row)
        <tr>
            <td width="12%" style="{{ $td }}">{{ $row['room'] }}</td>
            <td width="6%" style="{{ $td }} background-color: {{ $states[$row['status']][1] ?? '#ffffff' }}; font-weight: bold;" align="center">{{ $states[$row['status']][0] ?? $row['status_label'] }}</td>
            <td width="32%" style="{{ $td }}">{{ $row['guest'] ?: '-' }}</td>
            <td width="9%" style="{{ $td }}">{{ $row['check_in']?->format('Y-m-d') ?: '-' }}</td>
            <td width="9%" style="{{ $td }}">{{ $row['check_out']?->format('Y-m-d') ?: '-' }}</td>
            <td width="5%" style="{{ $td }}" align="right">{{ $row['people'] }}</td>
            <td width="9%" style="{{ $td }}" align="right">{{ $row['advance'] > 0 ? $money($row['advance'], $row['currency']) : '-' }}</td>
            <td width="9%" style="{{ $td }}" align="right">{{ $row['total'] > 0 ? $money($row['total'], $row['currency']) : '-' }}</td>
            <td width="9%" style="{{ $td }}" align="right">{{ $row['balance'] > 0 ? $money($row['balance'], $row['currency']) : '-' }}</td>
        </tr>
        @endforeach
    @empty
        <tr><td style="{{ $td }}" colspan="9">Sin habitaciones activas.</td></tr>
    @endforelse
</table>

<p style="{{ $font }} color: #344b5f;"><strong>Estados:</strong> R = Reservada &nbsp; O = Ocupada &nbsp; L = Libre &nbsp; CO = Check-out &nbsp; CP = Check-out pendiente &nbsp; B = Bloqueada</p>
