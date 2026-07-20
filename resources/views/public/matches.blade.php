@include('public.partials.header', ['title' => 'Partidos programados · '.$company->name])
@include('public.partials.banner', [
    'heading' => 'Fixture de partidos',
    'eyebrow' => 'Programacion',
    'subheading' => 'Partidos organizados por jornada, equipo, hora y cancha de juego.',
])
<main class="public-shell content-section">
    <form class="filter-card" method="GET">
        <select name="filter_mode" onchange="this.form.submit()">
            <option value="matchday" @selected($filterMode === 'matchday')>Filtrar por jornada</option>
            <option value="team" @selected($filterMode === 'team')>Filtrar por equipo</option>
        </select>
        @if($filterMode === 'matchday')
            <select name="matchday_id" onchange="this.form.submit()">
                <option value="">Todas las jornadas</option>
                @foreach($matchdays as $matchday)
                    <option value="{{ $matchday->id }}" @selected($selectedMatchday?->is($matchday))>{{ $matchday->name ?: 'Jornada '.$matchday->number }}</option>
                @endforeach
            </select>
        @else
            <select name="team_id" onchange="this.form.submit()">
                <option value="">Todos los equipos</option>
                @foreach($teams as $team)
                    <option value="{{ $team->id }}" @selected($selectedTeam?->is($team))>{{ $team->name }}</option>
                @endforeach
            </select>
        @endif
    </form>
    @include('public.partials.fixture-board', ['matches' => $matches])
</main>
@include('public.partials.footer')
