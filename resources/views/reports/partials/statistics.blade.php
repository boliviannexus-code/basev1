@if ($statisticsTooLong)
    <div class="alert alert-warning mt-3">Selecciona un periodo de hasta 366 días para ver las estadísticas.</div>
@else
    @php
        $maxDaily = max(1, $statistics['daily']->max('income') ?? 0, $statistics['daily']->max('expenses') ?? 0);
        $maxSource = max(1, collect($statistics['sources'])->max('total') ?? 0);
        $pieMethods = $statistics['methods']->filter(fn ($method) => $method['income'] > 0)->values();
        $pieTotal = $pieMethods->sum('income');
        $pieColors = ['#2b9867', '#3975d7', '#df8a28', '#a65ab3', '#db5d5d', '#24a2a2', '#b79024', '#6473bd', '#be6b89', '#668e44'];
        $piePosition = 0;
        $pieStops = $pieMethods->map(function ($method, $index) use (&$piePosition, $pieTotal, $pieColors) {
            $start = $piePosition;
            $piePosition += $method['income'] / $pieTotal * 100;
            return $pieColors[$index % count($pieColors)].' '.number_format($start, 5, '.', '').'% '.number_format($piePosition, 5, '.', '').'%';
        })->implode(', ');

    @endphp
    @if ($statistics['exceeds_detail_limit'])
        <div class="alert alert-info mt-3">Las estadísticas incluyen todos los movimientos. El reporte detallado muestra hasta 500 registros por tipo.</div>
    @endif
    <div class="card mt-3" data-economic-chart>
        <div class="card-header"><h3 class="card-title">Ingresos y egresos por día</h3></div>
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center gap-2 small mb-3" aria-label="Series del gráfico">
                <button type="button" class="economic-series-toggle" data-economic-series="income" aria-pressed="true"><span class="economic-legend economic-legend-income"></span> Ingresos</button>
                <button type="button" class="economic-series-toggle" data-economic-series="expense" aria-pressed="true"><span class="economic-legend economic-legend-expense"></span> Egresos</button>
                <button type="button" class="economic-series-toggle" data-economic-series="values" aria-pressed="true">Importes en {{ company_currency_code() }}</button>
            </div>
            <div class="economic-chart-scroll" role="img" aria-label="Barras con importes de ingresos y egresos por día. Los importes también se encuentran en la tabla inferior.">
                <div class="economic-chart">
                    @foreach ($statistics['daily'] as $day)
                        <div class="economic-chart-day" title="{{ $day['date'] }} · Ingresos {{ money_format_decimal($day['income']) }} · Egresos {{ money_format_decimal($day['expenses']) }}">
                            <div class="economic-chart-bars">
                                <span class="economic-bar-column" data-economic-bar="income"><span class="economic-bar economic-bar-income" style="height: {{ max($day['income'] > 0 ? 1 : 0, $day['income'] / $maxDaily * 100) }}%"><span class="economic-bar-value">{{ money_format_decimal($day['income']) }}</span></span></span>
                                <span class="economic-bar-column" data-economic-bar="expense"><span class="economic-bar economic-bar-expense" style="height: {{ max($day['expenses'] > 0 ? 1 : 0, $day['expenses'] / $maxDaily * 100) }}%"><span class="economic-bar-value">{{ money_format_decimal($day['expenses']) }}</span></span></span>
                            </div>
                            <span class="economic-chart-date">{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
            <details class="mt-3"><summary>Ver importes por día</summary>
                <div class="table-responsive"><table class="table table-sm mt-2"><thead><tr><th>Fecha</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead><tbody>
                    @foreach ($statistics['daily'] as $day)
                        <tr><td>{{ \Carbon\CarbonImmutable::parse($day['date'])->format('d/m/Y') }}</td><td class="text-end">{{ money_format_decimal($day['income']) }}</td><td class="text-end">{{ money_format_decimal($day['expenses']) }}</td><td class="text-end">{{ money_format_decimal($day['income'] - $day['expenses']) }}</td></tr>
                    @endforeach
                </tbody></table></div>
            </details>
        </div>
    </div>
    <div class="row g-3 mt-1">
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Origen de ingresos</h3></div><div class="card-body">
            @foreach ($statistics['sources'] as $source)
                <div class="d-flex justify-content-between"><span>{{ $source['name'] }}</span><strong>{{ money_format_decimal($source['total']) }} {{ company_currency_code() }}</strong></div>
                <div class="progress mb-3" role="progressbar" aria-label="{{ $source['name'] }}" aria-valuenow="{{ $source['total'] }}" aria-valuemin="0" aria-valuemax="{{ $maxSource }}"><div class="progress-bar bg-success" style="width: {{ $source['total'] / $maxSource * 100 }}%"></div></div>
            @endforeach
        </div></div></div>
        <div class="col-lg-6"><div class="card h-100"><div class="card-header"><h3 class="card-title">Ingresos por método de pago</h3></div><div class="card-body">
            @if ($pieTotal > 0)
                <div class="economic-pie-layout">
                    <div class="economic-pie" style="background: conic-gradient({{ $pieStops }});" role="img" aria-label="Distribución de ingresos por método de pago. Los importes y porcentajes se detallan a continuación."></div>
                    <ul class="economic-pie-legend list-unstyled mb-0">
                        @foreach ($pieMethods as $method)
                            <li><span class="economic-legend" style="background-color: {{ $pieColors[$loop->index % count($pieColors)] }}"></span> <span>{{ $method['name'] }}</span><strong>{{ money_format_decimal($method['income']) }} {{ company_currency_code() }} · {{ number_format($method['income'] / $pieTotal * 100, 1) }}%</strong></li>
                        @endforeach
                    </ul>
                </div>
            @else
                <p class="text-body-secondary mb-0">No hay ingresos para mostrar en la torta.</p>
            @endif
            <details class="mt-3"><summary>Ver ingresos, egresos y saldo por método</summary>
                <div class="table-responsive"><table class="table table-sm mt-2 mb-0"><thead><tr><th>Método</th><th class="text-end">Ingresos</th><th class="text-end">Egresos</th><th class="text-end">Saldo</th></tr></thead><tbody>
                    @forelse ($statistics['methods'] as $method)
                        <tr><td>{{ $method['name'] }}</td><td class="text-end">{{ money_format_decimal($method['income']) }}</td><td class="text-end">{{ money_format_decimal($method['expenses']) }}</td><td class="text-end">{{ money_format_decimal($method['balance']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-body-secondary">Sin movimientos para los filtros seleccionados.</td></tr>
                    @endforelse
                </tbody></table></div>
            </details>
        </div></div></div>
    </div>
@endif
