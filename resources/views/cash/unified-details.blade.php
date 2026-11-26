@if (isset($cashSummary['reservation_total']))
    <div class="card my-3">
        <div class="card-header"><h3 class="card-title">Resumen de caja unificada</h3></div>
        <div class="table-responsive"><table class="table table-sm table-vcenter mb-0">
            <thead><tr><th>Concepto</th><th class="text-end">Total</th></tr></thead>
            <tbody>
                <tr><td>Ventas e ingresos POS</td><td class="text-end">{{ money_format_decimal($cashSummary['sales_total'] ?? 0) }}</td></tr>
                <tr><td>Ingresos directos de espacios</td><td class="text-end">{{ money_format_decimal($cashSummary['direct_total'] ?? 0) }}</td></tr>
                <tr><td>Cobros de alojamiento</td><td class="text-end">{{ money_format_decimal($cashSummary['lodging_total'] ?? 0) }}</td></tr>
                <tr><td>Cobros de reservas</td><td class="text-end">{{ money_format_decimal($cashSummary['reservation_total'] ?? 0) }}</td></tr>
                <tr><td>Egresos totales</td><td class="text-end">{{ money_format_decimal($cashSummary['expenses'] ?? 0) }}</td></tr>
                <tr><td>Efectivo esperado</td><td class="text-end fw-bold">{{ money_format_decimal($cashSummary['available'] ?? 0) }}</td></tr>
            </tbody>
        </table></div>
    </div>
@endif
