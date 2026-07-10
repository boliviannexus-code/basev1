@php
    $homeName = $match->homeTeam?->name ?? $match->home_seed ?? 'Equipo A';
    $awayName = $match->awayTeam?->name ?? $match->away_seed ?? 'Equipo B';
@endphp

<div data-refresh-container>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('match-reports.matchdays.show', $match->matchdayDate->matchday) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Partidos
        </a>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('match-reports.finish', $report) }}" data-confirm-match-finish>
                @csrf
                <button class="btn btn-success btn-sm" type="submit">
                    <i class="ti ti-device-floppy me-1"></i>
                    Finalizar el partido
                </button>
            </form>
            <span class="badge text-bg-success fs-5 px-4">Partido iniciado</span>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 text-center align-items-center">
                <div class="col-5 text-end fw-semibold text-success">GOLES <i class="ti ti-ball-football"></i></div>
                <div class="col-2 fw-bold">{{ $summary['home_goals'] }} &nbsp; {{ $summary['away_goals'] }}</div>
                <div class="col-5 text-start fw-semibold text-success"><i class="ti ti-ball-football"></i> GOLES</div>

                <div class="col-5 text-end fw-semibold text-warning">AMARILLAS</div>
                <div class="col-2 fw-bold text-warning">{{ $summary['home_yellow_cards'] }} &nbsp; {{ $summary['away_yellow_cards'] }}</div>
                <div class="col-5 text-start fw-semibold text-warning">AMARILLAS</div>

                <div class="col-5 text-end fw-semibold text-danger">ROJAS</div>
                <div class="col-2 fw-bold text-danger">{{ $summary['home_red_cards'] }} &nbsp; {{ $summary['away_red_cards'] }}</div>
                <div class="col-5 text-start fw-semibold text-danger">ROJAS</div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        @include('match-reports.partials.team-panel', [
            'side' => 'home',
            'teamName' => $homeName,
            'players' => $homePlayers,
            'options' => $homeOptions,
            'report' => $report,
            'refreshUrl' => $refreshUrl,
            'tone' => 'bg-teal text-white',
        ])
        @include('match-reports.partials.team-panel', [
            'side' => 'away',
            'teamName' => $awayName,
            'players' => $awayPlayers,
            'options' => $awayOptions,
            'report' => $report,
            'refreshUrl' => $refreshUrl,
            'tone' => 'bg-orange text-white',
        ])
    </div>
</div>
