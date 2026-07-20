@include('public.partials.header', ['title' => 'Kardex de jugador · '.$company->name])
@include('public.partials.banner', [
    'heading' => 'Kardex de jugador',
    'eyebrow' => 'Consulta publica',
    'subheading' => 'Busca un jugador y revisa su historial deportivo registrado en la liga.',
])
<main class="public-shell content-section">
    <form class="filter-card" method="GET"><input name="q" value="{{ $query }}" placeholder="Nombre, CI o codigo"><button type="submit">Buscar</button></form>
    @if($players->count() > 1)
        <form class="filter-card" method="GET">
            <input type="hidden" name="q" value="{{ $query }}">
            <select name="player_id" onchange="this.form.submit()">
                @foreach($players as $option)
                    <option value="{{ $option->id }}" @selected($player?->is($option))>{{ $option->full_name }} · {{ $option->internal_code ?? $option->ci }}</option>
                @endforeach
            </select>
        </form>
    @endif
    @if($player && $kardex)
        <section class="public-card">
            <h2>{{ $player->full_name }}</h2>
            <p>Codigo {{ $player->internal_code ?? '-' }} · CI {{ $player->ci ?? '-' }} · Nacimiento {{ $player->birth_date?->format('d/m/Y') ?? '-' }} · Edad {{ $player->age() ?? '-' }}</p>
            <p class="muted">{{ $player->is_active ? 'Jugador activo' : 'Jugador inactivo' }}</p>
        </section>
        <section class="stats-grid">
            <div class="stat-box"><span>Equipos</span><strong>{{ $kardex['summary']['teams'] }}</strong></div>
            <div class="stat-box"><span>Habilitaciones</span><strong>{{ $kardex['summary']['habilitations'] }}</strong></div>
            <div class="stat-box"><span>Partidos</span><strong>{{ $kardex['summary']['matches'] }}</strong></div>
            <div class="stat-box"><span>Goles</span><strong>{{ $kardex['summary']['goals'] }}</strong></div>
            <div class="stat-box"><span>Amarillas</span><strong>{{ $kardex['summary']['yellow_cards'] }}</strong></div>
            <div class="stat-box"><span>Rojas</span><strong>{{ $kardex['summary']['red_cards'] }}</strong></div>
            <div class="stat-box"><span>Pases</span><strong>{{ $kardex['summary']['transfers'] }}</strong></div>
            <div class="stat-box"><span>Castigos</span><strong>{{ $kardex['summary']['punishments'] }}</strong></div>
        </section>
        <section class="public-card wide">
            <h2>Resumen por torneo</h2>
            <table class="public-table">
                <thead><tr><th>Torneo</th><th>Equipos</th><th>Hab.</th><th>PJ</th><th>Goles</th><th>TA</th><th>TR</th><th>Susp.</th><th>Pases</th></tr></thead>
                <tbody>
                    @forelse($kardex['tournamentSummary'] as $row)
                        <tr><td>{{ $row['tournament'] }}</td><td>{{ $row['teams_label'] }}</td><td>{{ $row['habilitations'] }}</td><td>{{ $row['matches'] }}</td><td>{{ $row['goals'] }}</td><td>{{ $row['yellow_cards'] }}</td><td>{{ $row['red_cards'] }}</td><td>{{ $row['suspended_matches'] }}</td><td>{{ $row['transfers'] }}</td></tr>
                    @empty
                        <tr><td colspan="9">Sin movimiento por torneo.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <section class="public-card wide">
            <h2>Historial de equipos</h2>
            <table class="public-table">
                <thead><tr><th>Equipo</th><th>Division</th><th>Estado</th><th>Desde</th><th>Hasta</th></tr></thead>
                <tbody>
                    @forelse($kardex['teamHistory'] as $row)
                        <tr><td>{{ $row->team?->name ?? '-' }}</td><td>{{ $row->division?->name ?? '-' }}</td><td>{{ $row->status }}</td><td>{{ $row->joined_at?->format('d/m/Y') ?? '-' }}</td><td>{{ $row->ended_at?->format('d/m/Y') ?? '-' }}</td></tr>
                    @empty
                        <tr><td colspan="5">Sin historial de equipos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
        <section class="public-card wide">
            <h2>Detalle historico</h2>
            <table class="public-table">
                <thead><tr><th>Fecha</th><th>Tipo</th><th>Equipo</th><th>Competicion</th><th>Detalle</th><th>Datos</th></tr></thead>
                <tbody>
                    @forelse($kardex['details'] as $row)
                        <tr><td>{{ $row['date']?->format('d/m/Y') ?? '-' }}</td><td>{{ $row['type'] }}</td><td>{{ $row['team'] }}</td><td>{{ $row['competition'] }}</td><td>{{ $row['detail'] }}</td><td>{{ $row['stats'] }}</td></tr>
                    @empty
                        <tr><td colspan="6">Sin detalle historico.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>
    @elseif($query !== '')
        <section class="public-card"><p>No se encontraron datos para la busqueda.</p></section>
    @endif
</main>
@include('public.partials.footer')
