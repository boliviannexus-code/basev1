<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Segmento</th><th class="text-end">Res.</th><th class="text-end">Huesp.</th><th class="text-end">Ingresos</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="text-end">{{ $row['bookings'] }}</td>
                    <td class="text-end">{{ $row['guests'] }}</td>
                    <td class="text-end fw-semibold">{{ $money($row['revenue']) }}</td>
                </tr>
            @empty
                <tr><td class="text-center text-body-secondary py-3" colspan="4">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
