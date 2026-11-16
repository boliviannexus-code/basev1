<p>Cargos registrados por fecha del servicio; no representan cobros ni se suman a los ingresos de caja. El filtro por metodo de pago no aplica a este detalle.</p>
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle" cellpadding="4" cellspacing="0" border="1">
        <thead><tr><th>Fecha</th><th>Usuario</th><th>Categoria</th><th>Estancia / espacio / habitacion</th><th>Huesped</th><th>Detalle</th><th>Cantidad</th><th>Precio unitario</th><th>Monto</th></tr></thead>
        <tbody>
            @forelse ($stayExtraCharges as $charge)
                <tr>
                    <td>{{ $charge->date?->format('Y-m-d') }}</td>
                    <td>{{ $charge->creator?->name ?? 'Sin usuario' }}</td>
                    <td>{{ $charge->extraChargeCategory?->name ?? 'Sin categoria' }}</td>
                    <td>#{{ $charge->stay_id }} · {{ $charge->stay?->space?->title ?: $charge->stay?->space?->name ?: '-' }} · {{ $charge->stay?->room?->name ?: '-' }}</td>
                    <td>{{ $charge->stay?->holderGuest?->full_name ?? '-' }}</td>
                    <td>{{ $charge->description }}</td>
                    <td>{{ number_format((float) $charge->quantity, 2) }}</td>
                    <td>{{ number_format((float) $charge->unit_price, 2) }} {{ $charge->currency }}</td>
                    <td>{{ number_format((float) $charge->total, 2) }} {{ $charge->currency }}</td>
                </tr>
            @empty
                <tr><td colspan="9">Sin cargos extra de estancias para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@foreach ($stayExtraChargeTotals as $currency => $total)
    <p><strong>Subtotal cargos extra: {{ number_format($total, 2) }} {{ $currency }}</strong></p>
@endforeach
