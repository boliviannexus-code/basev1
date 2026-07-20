@if (! $selectedTournamentId || ! $selectedFixtureGroup)
    <div class="alert alert-info mb-0">
        Selecciona torneo, categoria y serie para listar los partidos generados por fixture.
    </div>
@else
    @php
        $roundLabel = function ($match): string {
            $label = $match->round_number
                ? 'Fecha '.$match->round_number
                : ($match->tie_number ? $match->stage.' · Llave '.$match->tie_number : $match->stage);

            return $label.($match->leg_number > 1 ? ' · Vuelta' : '');
        };
    @endphp

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Ronda</th>
                    <th>Equipos</th>
                    <th style="width: 9rem;">Horario</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($availableMatches as $match)
                    <tr>
                        <td>{{ $roundLabel($match) }}</td>
                        <td>
                            <span class="fw-semibold">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</span>
                            <span class="text-body-secondary mx-1">vs</span>
                            <span class="fw-semibold">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</span>
                        </td>
                        <td colspan="2">
                            @if ($matchday->status === 'finalized')
                                <span class="text-body-secondary small">Jornada finalizada</span>
                            @else
                            @can('matchdays.update')
                                <form class="d-flex justify-content-end gap-2" method="POST" action="{{ route('matchdays.dates.matches.store', array_filter([
                                    'date' => $date,
                                    'tournament_id' => $selectedTournamentId,
                                    'fixture_group' => $selectedFixtureGroup,
                                    'category_id' => $selectedCategoryId,
                                    'series' => $selectedSeries,
                                ])) }}">
                                    @csrf
                                    <input type="hidden" name="fixture_match_id" value="{{ $match->id }}">
                                    <input class="form-control form-control-sm" name="scheduled_time" type="time" required>
                                    <button class="btn btn-primary btn-sm" type="submit">
                                        Programar
                                    </button>
                                </form>
                            @else
                                <span class="text-body-secondary small">Sin permiso</span>
                            @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="4" message="No hay partidos pendientes para esta seleccion." />
                @endforelse
            </tbody>
        </table>
        @if ($availableMatches instanceof \Illuminate\Contracts\Pagination\Paginator && $availableMatches->hasPages())
            <div class="mt-3" data-fixture-pagination>
                {{ $availableMatches->links() }}
            </div>
        @endif
    </div>
@endif
