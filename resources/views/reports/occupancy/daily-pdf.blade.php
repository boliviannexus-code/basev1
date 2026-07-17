@php
    $font = 'font-family: dejavusans, sans-serif; font-size: 7px;';
    $th = 'border: 1px solid #555; background-color: #f1f3f5; font-weight: bold; padding: 4px;';
    $td = 'border: 1px solid #777; padding: 4px;';
    $money = fn ($value, $currency = 'BOB') => number_format((float) $value, 2, '.', ',').' '.$currency;
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

<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Habitaciones</th>
        <th style="{{ $th }}">Libres</th>
        <th style="{{ $th }}">Ocupadas</th>
        <th style="{{ $th }}">Reservadas</th>
        <th style="{{ $th }}">Bloqueadas</th>
        <th style="{{ $th }}">Desayunos</th>
        <th style="{{ $th }}">Saldo</th>
    </tr>
    <tr>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['rooms'] }}</td>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['free'] }}</td>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['occupied'] }}</td>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['reserved'] }}</td>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['blocked'] }}</td>
        <td style="{{ $td }}" align="right">{{ $dailyOccupancy['summary']['breakfasts'] }}</td>
        <td style="{{ $td }}" align="right">{{ number_format((float) $dailyOccupancy['summary']['balance'], 2, '.', ',') }}</td>
    </tr>
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Habitaciones del dia</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Alojamiento</th>
        <th style="{{ $th }}">Habitacion</th>
        <th style="{{ $th }}">Estado</th>
        <th style="{{ $th }}">Huesped / detalle</th>
        <th style="{{ $th }}">Check-in</th>
        <th style="{{ $th }}">Check-out</th>
        <th style="{{ $th }}" align="right">Pers.</th>
        <th style="{{ $th }}">Pago</th>
        <th style="{{ $th }}" align="right">Adelanto</th>
        <th style="{{ $th }}" align="right">Total</th>
        <th style="{{ $th }}" align="right">Saldo</th>
    </tr>
    @forelse ($dailyOccupancy['rooms'] as $row)
        <tr>
            <td style="{{ $td }}">{{ $row['space'] }}</td>
            <td style="{{ $td }}">{{ $row['room'] }}</td>
            <td style="{{ $td }}">{{ $row['status_label'] }}</td>
            <td style="{{ $td }}">{{ $row['guest'] ?: '-' }}</td>
            <td style="{{ $td }}">{{ $row['check_in']?->format('Y-m-d') ?: '-' }}</td>
            <td style="{{ $td }}">{{ $row['check_out']?->format('Y-m-d') ?: '-' }}</td>
            <td style="{{ $td }}" align="right">{{ $row['people'] }}</td>
            <td style="{{ $td }}">{{ $row['payment_status'] ?: '-' }}</td>
            <td style="{{ $td }}" align="right">{{ $row['advance'] > 0 ? $money($row['advance'], $row['currency']) : '-' }}</td>
            <td style="{{ $td }}" align="right">{{ $row['total'] > 0 ? $money($row['total'], $row['currency']) : '-' }}</td>
            <td style="{{ $td }}" align="right">{{ $row['balance'] > 0 ? $money($row['balance'], $row['currency']) : '-' }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="11">Sin habitaciones activas.</td></tr>
    @endforelse
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Desayunos para el dia</h2>
<p style="{{ $font }}">Calculados con la ocupabilidad de {{ $dailyOccupancy['previousDate']->format('d/m/Y') }}.</p>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Alojamiento</th>
        <th style="{{ $th }}">Habitacion</th>
        <th style="{{ $th }}">Huesped</th>
        <th style="{{ $th }}" align="right">Cantidad</th>
    </tr>
    @forelse ($dailyOccupancy['breakfasts'] as $row)
        <tr>
            <td style="{{ $td }}">{{ $row['space'] }}</td>
            <td style="{{ $td }}">{{ $row['room'] }}</td>
            <td style="{{ $td }}">{{ $row['guest'] }}</td>
            <td style="{{ $td }}" align="right">{{ $row['people'] }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="4">Sin desayunos.</td></tr>
    @endforelse
    <tr>
        <td style="{{ $td }}" colspan="3" align="right"><strong>Total desayunos</strong></td>
        <td style="{{ $td }}" align="right"><strong>{{ $dailyOccupancy['summary']['breakfasts'] }}</strong></td>
    </tr>
</table>
