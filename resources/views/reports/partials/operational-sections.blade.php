<div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ingresos directos" :value="money_format_decimal($totals['direct_incomes'])" icon="ti ti-cash" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Hospedaje" :value="money_format_decimal($totals['lodging'])" icon="ti ti-home-dollar" tone="success" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Reservas" :value="money_format_decimal($totals['reservations'])" icon="ti ti-calendar-dollar" tone="info" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Egresos" :value="money_format_decimal($totals['expenses'])" icon="ti ti-cash-banknote-off" tone="warning" /></div>
</div>

<div class="row g-3 mt-1">
    <div class="{{ $reportType === 'summary' ? 'col-lg-7' : 'col-12' }}">
        <x-ui.table-card title="Resumen por tipo de pago">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Tipo de pago</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @forelse ($methodSummary as $row)
                            <tr><td class="fw-semibold">{{ $row['name'] }}</td><td class="text-end text-success">{{ money_format_decimal($row['income']) }}</td><td class="text-end text-warning">{{ money_format_decimal($row['expenses']) }}</td><td class="text-end fw-semibold">{{ money_format_decimal($row['balance']) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="4">Sin movimientos para los filtros seleccionados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>
    </div>
    @if ($reportType === 'summary')
        <div class="col-lg-5">
            <x-ui.table-card title="Saldo por categoría">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Categoría</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @forelse ($categorySummary as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="text-end fw-semibold">{{ money_format_decimal($row['balance']) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="2">Sin movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    @endif
</div>

@if (in_array($reportType, ['summary', 'expenses'], true))
    <details class="card mt-3">
        <summary class="card-header d-flex align-items-center justify-content-between gap-3" style="cursor: pointer;">
            <span class="fw-semibold"><i class="ti ti-cash-banknote-off me-2 text-warning"></i>Detalle de egresos</span>
            <span class="badge text-bg-secondary">{{ $expenses->count() }} registros · {{ money_format_decimal($totals['expenses']) }}</span>
        </summary>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Usuario</th><th>Método</th><th>Categoría</th><th>Detalle</th><th class="text-end">Cantidad</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $expense->user?->name ?? $expense->responsible_name }}</td>
                            <td>{{ $expense->paymentMethod?->name ?: 'Efectivo' }}</td>
                            <td>{{ $expense->category?->name ?: '-' }}</td>
                            <td>{{ $expense->detail }}</td>
                            <td class="text-end">{{ number_format((float) ($expense->quantity ?? 1), 2) }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($expense->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin egresos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
@endif

@if (in_array($reportType, ['summary', 'lodging'], true))
    <details class="card mt-3">
        <summary class="card-header d-flex align-items-center justify-content-between gap-3" style="cursor: pointer;">
            <span class="fw-semibold"><i class="ti ti-home-dollar me-2 text-success"></i>Detalle de hospedaje</span>
            <span class="badge text-bg-secondary">{{ $lodging->count() }} registros · {{ money_format_decimal($totals['lodging']) }}</span>
        </summary>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Espacio</th><th>Comprobante</th><th>Huésped</th><th>Usuario</th><th>Método</th><th class="text-end">BOB</th></tr></thead>
                <tbody>
                    @forelse ($lodging as $payment)
                        <tr>
                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $payment->stay?->space?->title ?: $payment->stay?->space?->name ?: '-' }}</td>
                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                            <td>{{ $payment->stay?->holderGuest?->full_name ?? '-' }}</td>
                            <td>{{ $payment->user?->name ?? '-' }}</td>
                            <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin cobros de hospedaje.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
@endif

@if (in_array($reportType, ['summary', 'reservations'], true))
    <details class="card mt-3">
        <summary class="card-header d-flex align-items-center justify-content-between gap-3" style="cursor: pointer;">
            <span class="fw-semibold"><i class="ti ti-calendar-dollar me-2 text-info"></i>Detalle de reservas</span>
            <span class="badge text-bg-secondary">{{ $reservations->count() }} registros · {{ money_format_decimal($totals['reservations']) }}</span>
        </summary>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Espacio</th><th>Comprobante</th><th>Reserva</th><th>Usuario</th><th>Método</th><th>Referencia</th><th class="text-end">BOB</th></tr></thead>
                <tbody>
                    @forelse ($reservations as $payment)
                        <tr>
                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $payment->reservationGroup?->reservations?->pluck('space.title')->filter()->unique()->join(', ') ?: $payment->reservationGroup?->reservations?->pluck('space.name')->filter()->unique()->join(', ') ?: '-' }}</td>
                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                            <td>{{ $payment->reservationGroup?->code ?? '-' }}</td>
                            <td>{{ $payment->user?->name ?? '-' }}</td>
                            <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                            <td>{{ $payment->reference ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="8">Sin pagos de reserva.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </details>
@endif
