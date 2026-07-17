<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead><tr><th>Periodo</th><th class="text-end">Ocup.</th><th class="text-end">%</th></tr></thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="text-end">{{ $row['occupied'] }}/{{ $row['available'] }}</td>
                    <td style="min-width: 8rem;">
                        <div class="progress" style="height: .55rem;">
                            <div class="progress-bar" style="width: {{ $bar($row['rate']) }}%"></div>
                        </div>
                        <div class="small text-body-secondary text-end">{{ $pct($row['rate']) }}</div>
                    </td>
                </tr>
            @empty
                <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin datos.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
