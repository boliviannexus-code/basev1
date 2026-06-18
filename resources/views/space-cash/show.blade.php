@extends('layouts.admin')

@php
    $expectedCash = (float) ($cashSummary['available'] ?? 0);
    $countedCash = $cashRegister->closing_amount !== null ? (float) $cashRegister->closing_amount : null;
    $difference = $countedCash !== null ? $countedCash - $expectedCash : null;
@endphp

@section('title', 'Detalle de caja de espacios')
@section('page-title', 'Detalle de caja de espacios')
@section('page-subtitle', 'Cobros de estancias y reservas desde la apertura '.($cashRegister->opened_at?->format('Y-m-d H:i') ?? ''))

@section('content')
    <div class="d-flex justify-content-end mb-3">
        <a class="btn btn-outline-secondary" href="{{ route('space-cash.history') }}">Volver</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl"><x-ui.stat-card label="Base inicial" :value="money_format_decimal($cashSummary['opening'] ?? 0)" icon="ti ti-cash" /></div>
        <div class="col-sm-6 col-xl"><x-ui.stat-card label="Cobros estancia" :value="money_format_decimal($cashSummary['lodging_total'] ?? 0)" icon="ti ti-home-dollar" tone="success" /></div>
        <div class="col-sm-6 col-xl"><x-ui.stat-card label="Cobros reserva" :value="money_format_decimal($cashSummary['reservation_total'] ?? 0)" icon="ti ti-calendar-dollar" tone="success" /></div>
        <div class="col-sm-6 col-xl"><x-ui.stat-card label="Egresos" :value="money_format_decimal($cashSummary['expenses'] ?? 0)" icon="ti ti-cash-banknote-off" /></div>
        <div class="col-sm-6 col-xl"><x-ui.stat-card label="Efectivo esperado" :value="money_format_decimal($expectedCash)" icon="ti ti-report-money" /></div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-center">
                <div class="col-md-3"><div class="text-body-secondary">Empresa</div><div class="fw-semibold">{{ $cashRegister->company?->name ?? '-' }}</div></div>
                <div class="col-md-3"><div class="text-body-secondary">Usuario</div><div class="fw-semibold">{{ $cashRegister->user?->name ?? '-' }}</div></div>
                <div class="col-md-2"><div class="text-body-secondary">Estado</div><span class="badge text-bg-{{ $cashRegister->status === 'open' ? 'success' : 'secondary' }}">{{ $cashRegister->status === 'open' ? 'Abierta' : 'Cerrada' }}</span></div>
                <div class="col-md-2"><div class="text-body-secondary">Contado cierre</div><div class="fw-semibold">{{ $countedCash !== null ? money_format_decimal($countedCash) : '-' }}</div></div>
                <div class="col-md-2"><div class="text-body-secondary">Diferencia</div><div class="fw-semibold {{ $difference !== null && abs($difference) > 0.009 ? 'text-danger' : '' }}">{{ $difference !== null ? money_format_decimal($difference) : '-' }}</div></div>
            </div>
        </div>
    </div>

    <x-ui.table-card title="Cobros de estancias">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Comprobante</th><th>Estancia</th><th>Metodo</th><th>Referencia</th><th class="text-end">Monto</th><th class="text-end">BOB caja</th></tr></thead>
            <tbody>
                @forelse (($cashSummary['lodging_payments'] ?? []) as $payment)
                    <tr>
                        <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                        <td>{{ $payment->stay?->holderGuest?->full_name ?? 'Estancia' }}</td>
                        <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                        <td>{{ $payment->reference ?: '-' }}</td>
                        <td class="text-end">{{ money_format_decimal($payment->amount_original) }} {{ $payment->currency_original }}</td>
                        <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4" colspan="6">Sin cobros de estancias.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>

    <x-ui.table-card title="Cobros de reservas" class="mt-3">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Comprobante</th><th>Reserva</th><th>Metodo</th><th>Referencia</th><th class="text-end">Monto</th><th class="text-end">BOB caja</th></tr></thead>
            <tbody>
                @forelse (($cashSummary['reservation_payments'] ?? []) as $payment)
                    <tr>
                        <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                        <td>{{ $payment->reservationGroup?->code ?? 'Reserva' }}</td>
                        <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                        <td>{{ $payment->reference ?: '-' }}</td>
                        <td class="text-end">{{ money_format_decimal($payment->amount_original) }} {{ $payment->currency_original }}</td>
                        <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4" colspan="6">Sin cobros de reservas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>

    <x-ui.table-card title="Egresos" class="mt-3">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Encargado</th><th>Categoria</th><th>Detalle</th><th class="text-end">Monto</th></tr></thead>
            <tbody>
                @forelse (($cashSummary['expense_details'] ?? []) as $expense)
                    <tr>
                        <td>{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                        <td>{{ $expense->responsible_name }}</td>
                        <td>{{ $expense->category?->name ?: '-' }}</td>
                        <td>{{ $expense->detail }}</td>
                        <td class="text-end fw-semibold">{{ money_format_decimal($expense->amount) }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-4" colspan="5">Sin egresos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
