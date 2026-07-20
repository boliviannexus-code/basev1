@php
    $matchesByRound = $matches->groupBy(fn ($match) => $match->matchdayDate?->matchday?->id ?? 'sin-jornada');
@endphp

@if($matchesByRound->isNotEmpty())
    <section class="fixture-board">
        @foreach($matchesByRound as $roundMatches)
            @php
                $firstMatch = $roundMatches->first();
                $matchday = $firstMatch?->matchdayDate?->matchday;
                $roundTitle = $matchday?->name ?: ($matchday ? 'Jornada '.$matchday->number : 'Sin jornada');
            @endphp
            <article class="fixture-round">
                <header class="fixture-round-header">
                    <h2>{{ $roundTitle }}</h2>
                    <span class="fixture-count">{{ $roundMatches->count() }} partido(s)</span>
                </header>
                @foreach($roundMatches->groupBy(fn ($match) => $match->matchday_date_id ?? 'sin-fecha') as $dateMatches)
                    @php
                        $date = $dateMatches->first()?->matchdayDate;
                    @endphp
                    <section class="fixture-date-block">
                        <div class="fixture-court-bar">Cancha: {{ $date?->court?->name ?? 'Sin cancha' }}</div>
                        <div class="fixture-date-bar">{{ $date?->date?->translatedFormat('l d/m/Y') ?? 'Fecha por definir' }}</div>
                        <div class="fixture-list">
                            @foreach($dateMatches as $match)
                                @php
                                    $scheduledTime = $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i:s') : null;
                                    $fiscal = $scheduledTime
                                        ? $match->matchdayDate?->fiscals?->first(fn ($row) => $row->start_time <= $scheduledTime && $row->end_time > $scheduledTime)
                                        : null;
                                    $hasScore = $match->report && $match->report->home_score !== null && $match->report->away_score !== null;
                                    $isWalkover = $match->report?->status === 'walkover';
                                    $homeWalkover = $isWalkover && in_array($match->report?->wo_side, ['home', 'double'], true);
                                    $awayWalkover = $isWalkover && in_array($match->report?->wo_side, ['away', 'double'], true);
                                @endphp
                                <div class="fixture-match">
                                    <div class="fixture-time">
                                        {{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '--:--' }}
                                        <span>Horario</span>
                                    </div>
                                    <div class="fixture-teams">
                                        <div class="fixture-team home">
                                            @if($homeWalkover)
                                                <span class="fixture-wo">W.O.</span>
                                            @endif
                                            <span class="fixture-team-name">{{ $match->homeTeam?->name ?? 'Por definir' }}</span>
                                            @if($hasScore)
                                                <span class="fixture-team-score">{{ $match->report->home_score }}</span>
                                            @endif
                                        </div>
                                        <div class="fixture-vs">VS</div>
                                        <div class="fixture-team">
                                            @if($hasScore)
                                                <span class="fixture-team-score">{{ $match->report->away_score }}</span>
                                            @endif
                                            <span class="fixture-team-name">{{ $match->awayTeam?->name ?? 'Por definir' }}</span>
                                            @if($awayWalkover)
                                                <span class="fixture-wo">W.O.</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="fixture-detail">
                                        <strong>{{ $match->category?->name ?? 'Sin categoria' }}</strong>
                                        <span>Fiscal: {{ $fiscal?->team?->name ?? '-' }}</span>
                                        @if($isWalkover)
                                            <span>Definido por W.O.</span>
                                        @elseif($hasScore)
                                            <span>Resultado registrado</span>
                                        @else
                                            <span>Resultado pendiente</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </article>
        @endforeach
    </section>
@else
    <section class="fixture-empty">{{ $emptyMessage ?? 'No hay partidos programados con los filtros seleccionados.' }}</section>
@endif
