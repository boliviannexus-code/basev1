@php
    $singleColumn = $reportType !== 'summary';
@endphp

@if ($reportType === 'summary')
    <div class="row g-3 mt-1">
        <div class="col-md-3">
            <x-ui.stat-card label="Ventas" :value="money_format_decimal($salesSummary['total'])" icon="ti ti-receipt" />
        </div>
        <div class="col-md-3">
            <x-ui.stat-card label="Tickets" :value="$salesSummary['count']" icon="ti ti-cash-register" tone="success" />
        </div>
        <div class="col-md-3">
            <x-ui.stat-card label="Compras" :value="money_format_decimal($purchasesSummary['total'])" icon="ti ti-shopping-cart" tone="warning" />
        </div>
        <div class="col-md-3">
            <x-ui.stat-card label="Ordenes compra" :value="$purchasesSummary['count']" icon="ti ti-clipboard-list" tone="info" />
        </div>
    </div>
@endif

@if (in_array($reportType, ['summary', 'sales-products'], true))
    <div class="row g-3 mt-1">
        <div class="{{ $singleColumn ? 'col-12' : 'col-lg-6' }}">
            <x-ui.table-card title="Ventas por producto">
                @if ($singleColumn)
                    <div class="row g-3 p-3 border-bottom">
                        <div class="col-md-4"><span class="text-body-secondary">Ventas:</span> <strong>{{ $salesSummary['count'] }}</strong></div>
                        <div class="col-md-4"><span class="text-body-secondary">Descuento:</span> <strong>{{ money_format_decimal($salesSummary['discount']) }}</strong></div>
                        <div class="col-md-4"><span class="text-body-secondary">Total:</span> <strong>{{ money_format_decimal($salesSummary['total']) }}</strong></div>
                    </div>
                @endif
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead><tr><th>Producto</th><th class="text-end">Unidades</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse ($salesByProduct as $row)
                            <tr><td>{{ $row->name }}</td><td class="text-end">{{ number_format((float) $row->units) }}</td><td class="text-end fw-semibold">{{ money_format_decimal($row->total) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin ventas en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
        @if ($reportType === 'summary')
            <div class="col-lg-6">
                @include('reports.partials.table-purchases-products', ['singleColumn' => false])
            </div>
        @endif
    </div>
@endif

@if ($reportType === 'purchases-products')
    <div class="row g-3 mt-1">
        <div class="col-12">
            @include('reports.partials.table-purchases-products', ['singleColumn' => true])
        </div>
    </div>
@endif

@if (in_array($reportType, ['summary', 'stock', 'cash'], true))
    <div class="row g-3 mt-1">
        @if (in_array($reportType, ['summary', 'stock'], true))
            <div class="{{ $reportType === 'stock' ? 'col-12' : 'col-lg-7' }}">
                <x-ui.table-card title="Stock actual">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Almacen</th><th>Producto</th><th class="text-end">Stock</th></tr></thead>
                        <tbody>
                            @forelse ($stockRows as $row)
                                <tr><td>{{ $row->warehouse_name }}</td><td>{{ $row->product_name }}</td><td class="text-end fw-semibold">{{ number_format((float) $row->stock) }}</td></tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin stock registrado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
        @endif

        @if (in_array($reportType, ['summary', 'cash'], true))
            <div class="{{ $reportType === 'cash' ? 'col-12' : 'col-lg-5' }}">
                <x-ui.table-card title="Cajas">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Caja</th><th>Estado</th><th class="text-end">Ventas</th><th class="text-end">Egresos</th></tr></thead>
                        <tbody>
                            @forelse ($cashRows as $cashRegister)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">Caja de {{ $cashRegister->user?->name ?? '-' }}</div>
                                        <div class="text-body-secondary small">{{ $cashRegister->opened_at?->format('Y-m-d H:i') }} · {{ $cashRegister->user?->name }}</div>
                                    </td>
                                    <td><span class="badge text-bg-{{ $cashRegister->status === 'open' ? 'success' : 'secondary' }}">{{ $cashRegister->status === 'open' ? 'Abierta' : 'Cerrada' }}</span></td>
                                    <td class="text-end fw-semibold">{{ money_format_decimal($cashRegister->sales_total ?? 0) }}</td>
                                    <td class="text-end">{{ money_format_decimal($cashRegister->expenses_total ?? 0) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-3" colspan="4">Sin cajas en el periodo.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
        @endif
    </div>
@endif

@if (in_array($reportType, ['summary', 'voided-sales', 'voided-purchases'], true))
    <div class="row g-3 mt-1">
        @if (in_array($reportType, ['summary', 'voided-sales'], true))
            <div class="{{ $reportType === 'voided-sales' ? 'col-12' : 'col-lg-6' }}">
                <x-ui.table-card title="Ventas anuladas">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Comprobante</th><th>Almacen</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($voidedSales as $sale)
                                <tr>
                                    <td><div class="fw-semibold">{{ $sale->receipt_number }}</div><div class="text-body-secondary small">{{ $sale->sale_date?->format('Y-m-d') }} · {{ $sale->user?->name }}</div></td>
                                    <td>{{ $sale->warehouse?->name ?? '-' }}</td>
                                    <td class="text-end">{{ money_format_decimal($sale->total) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin ventas anuladas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
        @endif

        @if (in_array($reportType, ['summary', 'voided-purchases'], true))
            <div class="{{ $reportType === 'voided-purchases' ? 'col-12' : 'col-lg-6' }}">
                <x-ui.table-card title="Compras anuladas">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr><th>Referencia</th><th>Proveedor</th><th class="text-end">Total</th></tr></thead>
                        <tbody>
                            @forelse ($voidedPurchases as $purchase)
                                <tr>
                                    <td><div class="fw-semibold">{{ $purchase->reference }}</div><div class="text-body-secondary small">{{ $purchase->purchase_date?->format('Y-m-d') }} · {{ $purchase->warehouse?->name }}</div></td>
                                    <td>{{ $purchase->supplier?->name ?? '-' }}</td>
                                    <td class="text-end">{{ money_format_decimal($purchase->total) }}</td>
                                </tr>
                            @empty
                                <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin compras anuladas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-ui.table-card>
            </div>
        @endif
    </div>
@endif
