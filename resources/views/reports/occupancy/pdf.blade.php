@php
    $font = 'font-family: dejavusans, sans-serif; font-size: 7px;';
    $th = 'border: 1px solid #555; background-color: #f1f3f5; font-weight: bold; padding: 4px;';
    $td = 'border: 1px solid #777; padding: 4px;';
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
            <strong>Periodo:</strong> {{ $filters['from']->format('Y-m-d') }} al {{ $filters['to']->format('Y-m-d') }}<br>
            <strong>Fecha impresion:</strong> {{ now()->format('Y-m-d H:i') }}<br>
            <strong>Usuario:</strong> {{ $printedBy?->name ?? '-' }}<br>
            <strong>Email:</strong> {{ $printedBy?->email ?? '-' }}
        </td>
    </tr>
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Resumen de ocupabilidad</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Alojamientos</th>
        <th style="{{ $th }}">Capacidad-noches</th>
        <th style="{{ $th }}">Persona-noches ocupadas</th>
        <th style="{{ $th }}">Persona-noches reservadas</th>
        <th style="{{ $th }}">Noches bloqueadas</th>
        <th style="{{ $th }}">Ocupabilidad</th>
    </tr>
    <tr>
        <td style="{{ $td }}" align="right">{{ $occupancy['summary']['spaces'] }}</td>
        <td style="{{ $td }}" align="right">{{ $occupancy['summary']['capacity_nights'] }}</td>
        <td style="{{ $td }}" align="right">{{ $occupancy['summary']['occupied_person_nights'] }}</td>
        <td style="{{ $td }}" align="right">{{ $occupancy['summary']['reserved_person_nights'] }}</td>
        <td style="{{ $td }}" align="right">{{ $occupancy['summary']['blocked_nights'] }}</td>
        <td style="{{ $td }}" align="right">{{ number_format((float) $occupancy['summary']['occupancy_rate'], 2) }} %</td>
    </tr>
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Estadias</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Ingreso</th>
        <th style="{{ $th }}">Salida</th>
        <th style="{{ $th }}">Alojamiento</th>
        <th style="{{ $th }}">Habitacion</th>
        <th style="{{ $th }}">Huesped</th>
        <th style="{{ $th }}" align="right">Personas</th>
        <th style="{{ $th }}">Estado</th>
    </tr>
    @forelse ($occupancy['stays']->take(80) as $stay)
        <tr>
            <td style="{{ $td }}">{{ $stay->check_in_date?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $stay->check_out_date?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $stay->space?->title ?: $stay->space?->name }}</td>
            <td style="{{ $td }}">{{ $stay->room?->title ?: $stay->room?->name ?: '-' }}</td>
            <td style="{{ $td }}">{{ $stay->holderGuest?->full_name ?? '-' }}</td>
            <td style="{{ $td }}" align="right">{{ $stay->people_count }}</td>
            <td style="{{ $td }}">{{ $stay->status === 'occupied' ? 'Ocupada' : 'Check-out' }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="7">Sin estadias.</td></tr>
    @endforelse
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Reservas vigentes</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Ingreso</th>
        <th style="{{ $th }}">Salida</th>
        <th style="{{ $th }}">Codigo</th>
        <th style="{{ $th }}">Alojamiento</th>
        <th style="{{ $th }}">Huesped</th>
        <th style="{{ $th }}" align="right">Personas</th>
        <th style="{{ $th }}">Estado</th>
    </tr>
    @forelse ($occupancy['reservations']->take(80) as $reservation)
        <tr>
            <td style="{{ $td }}">{{ $reservation->check_in?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $reservation->check_out?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $reservation->code }}</td>
            <td style="{{ $td }}">{{ $reservation->space?->title ?: $reservation->space?->name }}</td>
            <td style="{{ $td }}">{{ $reservation->guest_name }}</td>
            <td style="{{ $td }}" align="right">{{ $reservation->guests }}</td>
            <td style="{{ $td }}">{{ $reservation->status }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="7">Sin reservas.</td></tr>
    @endforelse
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Bloqueos</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Inicio</th>
        <th style="{{ $th }}">Fin</th>
        <th style="{{ $th }}">Alojamiento</th>
        <th style="{{ $th }}">Habitacion</th>
        <th style="{{ $th }}">Cama</th>
        <th style="{{ $th }}">Motivo</th>
        <th style="{{ $th }}">Usuario</th>
    </tr>
    @forelse ($occupancy['blocks']->take(80) as $block)
        <tr>
            <td style="{{ $td }}">{{ $block->start_date?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $block->end_date?->format('Y-m-d') }}</td>
            <td style="{{ $td }}">{{ $block->space?->title ?: $block->space?->name }}</td>
            <td style="{{ $td }}">{{ $block->room?->title ?: $block->room?->name ?: '-' }}</td>
            <td style="{{ $td }}">{{ $block->bedUnit?->label ?: '-' }}</td>
            <td style="{{ $td }}">{{ $block->title ?: $block->description ?: $block->type ?: '-' }}</td>
            <td style="{{ $td }}">{{ $block->creator?->name ?? '-' }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="7">Sin bloqueos.</td></tr>
    @endforelse
</table>
