@include('public.partials.header', ['title' => 'Tabla de posiciones · '.$company->name])
@include('public.partials.banner', [
    'heading' => 'Tabla de posiciones',
    'eyebrow' => $selectedTournament?->name ?? 'Competencia',
    'subheading' => 'Consulta el rendimiento actualizado de los equipos por torneo, categoria y serie.',
])
<main class="public-shell content-section">
    <form class="filter-card" method="GET">
        <select name="tournament_id" onchange="this.form.submit()">@foreach($tournaments as $tournament)<option value="{{ $tournament->id }}" @selected($selectedTournament?->is($tournament))>{{ $tournament->name }}</option>@endforeach</select>
        <select name="group" onchange="this.form.submit()">@foreach($groups as $group)<option value="{{ $group['value'] }}" @selected(($selectedGroup['value'] ?? null) === $group['value'])>{{ $group['label'] }}</option>@endforeach</select>
    </form>
    <table class="public-table">
        <thead><tr><th>Pos</th><th>Equipo</th><th>PJ</th><th>GF</th><th>GC</th><th>DG</th><th>Pts</th><th>Partidos</th></tr></thead>
        <tbody>
            @foreach($standings as $row)
                <tr>
                    <td>{{ $row['position'] }}</td>
                    <td>{{ $row['team_name'] }}</td>
                    <td>{{ $row['played'] }}</td>
                    <td>{{ $row['goals_for'] }}</td>
                    <td>{{ $row['goals_against'] }}</td>
                    <td>{{ $row['goal_difference'] }}</td>
                    <td><strong>{{ $row['final_points'] }}</strong></td>
                    <td>
                        <a href="{{ route('public.standings.team-matches.pdf', [
                            'tenant' => request()->route('tenant'),
                            'tournament' => $selectedTournament,
                            'category' => $selectedGroup['category_id'],
                            'series' => $selectedGroup['series'],
                            'team' => $row['team_id'],
                        ]) }}" target="_blank" rel="noopener" title="Imprimir partidos de {{ $row['team_name'] }}">Imprimir</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</main>
@include('public.partials.footer')
