@php
    $font = 'font-family: dejavusans, sans-serif; font-size: 7px;';
    $th = 'border: 1px solid #555; background-color: #f1f3f5; font-weight: bold; padding: 4px;';
    $td = 'border: 1px solid #777; padding: 4px;';
    $money = fn ($value) => number_format((float) $value, 2, '.', ',');
    $showIncomes = in_array($reportType, ['summary', 'sales', 'lodging', 'reservations'], true);
    $showExpenses = in_array($reportType, ['summary', 'expenses'], true);
    $incomeDetailRows = $incomeRows->filter(fn ($row) => match ($reportType) {
        'sales' => $row['type'] === 'Venta POS',
        'lodging' => $row['type'] === 'Hospedaje',
        'reservations' => $row['type'] === 'Reserva',
        default => true,
    });
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
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Ventas POS</th>
        <th style="{{ $th }}">Hospedaje</th>
        <th style="{{ $th }}">Reservas</th>
        <th style="{{ $th }}">Egresos</th>
        <th style="{{ $th }}">Saldo</th>
    </tr>
    <tr>
        <td style="{{ $td }}" align="right">{{ $money($totals['sales']) }}</td>
        <td style="{{ $td }}" align="right">{{ $money($totals['lodging']) }}</td>
        <td style="{{ $td }}" align="right">{{ $money($totals['reservations']) }}</td>
        <td style="{{ $td }}" align="right">{{ $money($totals['expenses']) }}</td>
        <td style="{{ $td }}" align="right">{{ $money($totals['sales'] + $totals['direct_incomes'] + $totals['lodging'] + $totals['reservations'] - $totals['expenses']) }}</td>
    </tr>
</table>

<br>
<h2 style="{{ $font }} font-size: 12px;">Resumen por metodo</h2>
<table cellpadding="4" cellspacing="0" style="{{ $font }}">
    <tr>
        <th style="{{ $th }}">Metodo</th>
        <th style="{{ $th }}" align="right">Ingresos</th>
        <th style="{{ $th }}" align="right">Egresos</th>
        <th style="{{ $th }}" align="right">Saldo</th>
    </tr>
    @forelse ($methodSummary as $row)
        <tr>
            <td style="{{ $td }}">{{ $row['name'] }}</td>
            <td style="{{ $td }}" align="right">{{ $money($row['income']) }}</td>
            <td style="{{ $td }}" align="right">{{ $money($row['expenses']) }}</td>
            <td style="{{ $td }}" align="right">{{ $money($row['balance']) }}</td>
        </tr>
    @empty
        <tr><td style="{{ $td }}" colspan="4">Sin movimientos.</td></tr>
    @endforelse
</table>

@if ($showIncomes)
    <br>
    <h2 style="{{ $font }} font-size: 12px;">Detalle de ingresos</h2>
    <table cellpadding="4" cellspacing="0" style="{{ $font }}">
        <tr>
            <th style="{{ $th }}">Fecha</th>
            <th style="{{ $th }}">Tipo</th>
            <th style="{{ $th }}">Responsable</th>
            <th style="{{ $th }}">Metodo</th>
            <th style="{{ $th }}">Categoria</th>
            <th style="{{ $th }}">Detalle</th>
            <th style="{{ $th }}" align="right">Monto</th>
        </tr>
        @forelse ($incomeDetailRows->take(120) as $row)
            <tr>
                <td style="{{ $td }}">{{ $row['date']?->format('Y-m-d H:i') }}</td>
                <td style="{{ $td }}">{{ $row['type'] }}</td>
                <td style="{{ $td }}">{{ $row['responsible'] ?: '-' }}</td>
                <td style="{{ $td }}">{{ $row['method'] ?: '-' }}</td>
                <td style="{{ $td }}">{{ $row['category'] ?: '-' }}</td>
                <td style="{{ $td }}">{{ $row['detail'] ?: '-' }}</td>
                <td style="{{ $td }}" align="right">{{ $money($row['amount']) }}</td>
            </tr>
        @empty
            <tr><td style="{{ $td }}" colspan="7">Sin ingresos.</td></tr>
        @endforelse
    </table>
@endif

@if ($showExpenses)
    <br>
    <h2 style="{{ $font }} font-size: 12px;">Detalle de egresos</h2>
    <table cellpadding="4" cellspacing="0" style="{{ $font }}">
        <tr>
            <th style="{{ $th }}">Fecha</th>
            <th style="{{ $th }}">Modulo</th>
            <th style="{{ $th }}">Responsable</th>
            <th style="{{ $th }}">Metodo</th>
            <th style="{{ $th }}">Categoria</th>
            <th style="{{ $th }}">Detalle</th>
            <th style="{{ $th }}" align="right">Monto</th>
        </tr>
        @forelse ($expenses->take(120) as $expense)
            <tr>
                <td style="{{ $td }}">{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                <td style="{{ $td }}">{{ $expense->source_label }}</td>
                <td style="{{ $td }}">{{ $expense->user?->name ?? $expense->responsible_name }}</td>
                <td style="{{ $td }}">{{ $expense->paymentMethod?->name ?: 'Efectivo' }}</td>
                <td style="{{ $td }}">{{ $expense->category?->name ?: '-' }}</td>
                <td style="{{ $td }}">{{ $expense->detail }}</td>
                <td style="{{ $td }}" align="right">{{ $money($expense->amount) }}</td>
            </tr>
        @empty
            <tr><td style="{{ $td }}" colspan="7">Sin egresos.</td></tr>
        @endforelse
    </table>
@endif
