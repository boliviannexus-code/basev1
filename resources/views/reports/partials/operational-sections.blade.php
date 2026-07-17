<div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Ventas POS" :value="money_format_decimal($totals['sales'])" icon="ti ti-receipt" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Hospedaje" :value="money_format_decimal($totals['lodging'])" icon="ti ti-home-dollar" tone="success" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Reservas" :value="money_format_decimal($totals['reservations'])" icon="ti ti-calendar-dollar" tone="info" /></div>
    <div class="col-sm-6 col-xl-3"><x-ui.stat-card label="Egresos" :value="money_format_decimal($totals['expenses'])" icon="ti ti-cash-banknote-off" tone="warning" /></div>
</div>

@if (in_array($reportType, ['summary', 'sales'], true))
    <x-ui.table-card title="Ventas POS" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Usuario</th><th>Metodos</th><th class="text-end">Total</th></tr></thead>
                <tbody>
                    @forelse ($sales as $sale)
                        <tr>
                            <td>{{ $sale->sale_date?->format('Y-m-d H:i') }}</td>
                            <td class="fw-semibold">{{ $sale->receipt_number }}</td>
                            <td>{{ $sale->user?->name ?? '-' }}</td>
                            <td>{{ $sale->payments->pluck('payment_method_name')->filter()->unique()->join(', ') ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($sale->total) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="5">Sin ventas en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endif

@if (in_array($reportType, ['summary', 'expenses'], true))
    <x-ui.table-card title="Egresos" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Modulo</th><th>Usuario</th><th>Metodo</th><th>Categoria</th><th>Detalle</th><th class="text-end">Cantidad</th><th class="text-end">Monto</th></tr></thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->spent_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $expense->source_label }}</td>
                            <td>{{ $expense->user?->name ?? $expense->responsible_name }}</td>
                            <td>{{ $expense->paymentMethod?->name ?: 'Efectivo' }}</td>
                            <td>{{ $expense->category?->name ?: '-' }}</td>
                            <td>{{ $expense->detail }}</td>
                            <td class="text-end">{{ number_format((float) ($expense->quantity ?? 1), 2) }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($expense->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="8">Sin egresos en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endif

@if (in_array($reportType, ['summary', 'lodging'], true))
    <x-ui.table-card title="Hospedaje" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Modulo</th><th>Comprobante</th><th>Huesped</th><th>Usuario</th><th>Metodo</th><th class="text-end">BOB</th></tr></thead>
                <tbody>
                    @forelse ($lodging as $payment)
                        <tr>
                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $payment->source_label }}</td>
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
    </x-ui.table-card>
@endif

@if (in_array($reportType, ['summary', 'reservations'], true))
    <x-ui.table-card title="Reservas" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Fecha</th><th>Comprobante</th><th>Reserva</th><th>Usuario</th><th>Metodo</th><th>Referencia</th><th class="text-end">BOB</th></tr></thead>
                <tbody>
                    @forelse ($reservations as $payment)
                        <tr>
                            <td>{{ $payment->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="fw-semibold">{{ $payment->receipt_number }}</td>
                            <td>{{ $payment->reservationGroup?->code ?? '-' }}</td>
                            <td>{{ $payment->user?->name ?? '-' }}</td>
                            <td>{{ $payment->paymentMethod?->name ?? '-' }}</td>
                            <td>{{ $payment->reference ?: '-' }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($payment->amount_bob) }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin pagos de reserva.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endif

@if (in_array($reportType, ['summary', 'cash'], true))
    <x-ui.table-card title="Cajas" class="mt-3">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Apertura</th><th>Cierre</th><th>Modulo</th><th>Usuario</th><th>Estado</th><th class="text-end">Inicial</th><th class="text-end">Cierre</th></tr></thead>
                <tbody>
                    @forelse ($cashRegisters as $register)
                        <tr>
                            <td>{{ $register->opened_at?->format('Y-m-d H:i') }}</td>
                            <td>{{ $register->closed_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>{{ $register->source_label }}</td>
                            <td>{{ $register->user?->name ?? '-' }}</td>
                            <td>{{ $register->status === 'open' ? 'Abierta' : 'Cerrada' }}</td>
                            <td class="text-end">{{ money_format_decimal($register->opening_amount) }}</td>
                            <td class="text-end fw-semibold">{{ $register->closing_amount !== null ? money_format_decimal($register->closing_amount) : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin cajas en el periodo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endif

@if ($reportType === 'summary')
    <div class="row g-3 mt-3">
        <div class="col-lg-6">
            <x-ui.table-card title="Saldo por metodo">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Metodo</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @foreach ($methodSummary as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ money_format_decimal($row['income']) }}</td><td class="text-end">{{ money_format_decimal($row['expenses']) }}</td><td class="text-end fw-semibold">{{ money_format_decimal($row['balance']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
        <div class="col-lg-6">
            <x-ui.table-card title="Saldo por categoria">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Categoria</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead>
                    <tbody>
                        @foreach ($categorySummary as $row)
                            <tr><td>{{ $row['name'] }}</td><td class="text-end">{{ money_format_decimal($row['income']) }}</td><td class="text-end">{{ money_format_decimal($row['expenses']) }}</td><td class="text-end fw-semibold">{{ money_format_decimal($row['balance']) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>
@endif
