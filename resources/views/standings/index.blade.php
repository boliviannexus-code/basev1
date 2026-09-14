@extends('layouts.admin')

@section('title', 'Tabla de posiciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tabla de posiciones')
@section('page-subtitle', 'Estadisticas por torneo, categoria y serie')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" action="{{ route('standings.index') }}" data-standings-filter>
                <div class="col-lg-6">
                    <label class="form-label" for="standings-tournament">Torneo</label>
                    <select class="form-select" id="standings-tournament" name="tournament_id" data-standings-tournament>
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}" @selected($selectedTournament?->is($tournament))>
                                {{ $tournament->name }} · {{ $tournament->season?->name ?? 'Sin gestion' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-6">
                    <label class="form-label" for="standings-group">Categoria y serie</label>
                    <select class="form-select" id="standings-group" name="group" data-standings-group @disabled($groups->isEmpty())>
                        @foreach ($groups as $group)
                            <option value="{{ $group['value'] }}" @selected(($selectedGroup['value'] ?? null) === $group['value'])>
                                {{ $group['label'] }} · {{ $group['teams_count'] }} equipos
                            </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div data-standings-results aria-live="polite">
    @if (! $selectedTournament)
        <div class="alert alert-info">No hay torneos registrados para mostrar posiciones.</div>
    @elseif (! $selectedGroup)
        <div class="alert alert-warning">El torneo seleccionado no tiene equipos inscritos por categoria y serie.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="text-body-secondary small text-uppercase fw-semibold">Torneo</div>
                        <div class="h3 mb-0">{{ $selectedTournament->name }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="text-body-secondary small text-uppercase fw-semibold">Gestion</div>
                        <div class="h3 mb-0">{{ $selectedTournament->season?->name ?? '-' }}</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="text-body-secondary small text-uppercase fw-semibold">Categoria y serie</div>
                        <div class="h3 mb-0">{{ $selectedGroup['label'] }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end mb-3">
            <a class="btn btn-success" href="{{ route('standings.pdf', ['tournament_id' => $selectedTournament->id, 'group' => $selectedGroup['value']]) }}" target="_blank" rel="noopener">
                <i class="ti ti-printer me-1"></i>
                Imprimir tabla
            </a>
        </div>

        <x-ui.table-card title="Posiciones">
            <div class="table-responsive">
                <table class="table table-hover table-vcenter align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 4rem;">Pos</th>
                            <th>Equipo</th>
                            <th class="text-center" title="Partidos jugados">PJ</th>
                            <th class="text-center" title="Partidos ganados">PG</th>
                            <th class="text-center" title="Partidos empatados">PE</th>
                            <th class="text-center" title="Partidos perdidos">PP</th>
                            <th class="text-center" title="Goles a favor">GF</th>
                            <th class="text-center" title="Goles en contra">GC</th>
                            <th class="text-center" title="Diferencia de gol">DG</th>
                            <th class="text-center" title="Walk over">W.O.</th>
                            <th class="text-center" title="Puntos deportivos">PTS</th>
                            <th class="text-center" title="Observaciones administrativas">OBS</th>
                            <th class="text-center fs-3" title="Puntos finales">PTS FINAL</th>
                            @can('standings.adjust')
                                <th class="text-end" style="width: 7rem;">Ajuste</th>
                            @endcan
                            <th class="text-end" style="width: 7rem;">Partidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($standings as $row)
                            <tr>
                                <td class="text-center">
                                    <span class="badge text-bg-{{ $row['position'] <= 3 ? 'primary' : 'light border text-body' }}">
                                        {{ $row['position'] }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $row['team_name'] }}</div>
                                    @if ($row['team_number'])
                                        <div class="text-body-secondary small">Nro. {{ $row['team_number'] }}</div>
                                    @endif
                                </td>
                                <td class="text-center">{{ $row['played'] }}</td>
                                <td class="text-center">{{ $row['won'] }}</td>
                                <td class="text-center">{{ $row['drawn'] }}</td>
                                <td class="text-center">{{ $row['lost'] }}</td>
                                <td class="text-center">{{ $row['goals_for'] }}</td>
                                <td class="text-center">{{ $row['goals_against'] }}</td>
                                <td class="text-center fw-semibold {{ $row['goal_difference'] < 0 ? 'text-danger' : 'text-success' }}">
                                    {{ $row['goal_difference'] > 0 ? '+' : '' }}{{ $row['goal_difference'] }}
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-{{ $row['walkovers'] > 0 ? 'danger' : 'light border text-body' }}">{{ $row['walkovers'] }}</span>
                                </td>
                                <td class="text-center fw-bold">{{ $row['points'] }}</td>
                                <td class="text-center fw-bold {{ $row['adjustment_points'] < 0 ? 'text-danger' : ($row['adjustment_points'] > 0 ? 'text-success' : 'text-body-secondary') }}">
                                    {{ $row['adjustment_points'] > 0 ? '+' : '' }}{{ $row['adjustment_points'] }}
                                </td>
                                <td class="text-center fs-3 fw-bold text-primary">{{ $row['final_points'] }}</td>
                                @can('standings.adjust')
                                    <td class="text-end">
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#standing-adjustment-{{ $row['team_id'] }}">
                                            <i class="ti ti-scale me-1"></i>
                                            Puntos
                                        </button>
                                    </td>
                                @endcan
                                <td class="text-end">
                                    <a class="btn btn-outline-success btn-sm" href="{{ route('standings.team-matches.pdf', [
                                        'tournament' => $selectedTournament,
                                        'category' => $selectedGroup['category_id'],
                                        'series' => $selectedGroup['series'],
                                        'team' => $row['team_id'],
                                    ]) }}" target="_blank" rel="noopener" title="Imprimir partidos de {{ $row['team_name'] }}">
                                        <i class="ti ti-printer me-1"></i>
                                        Partidos
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="{{ auth()->user()?->can('standings.adjust') ? 15 : 14 }}" message="No hay equipos inscritos para esta serie." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>

        @can('standings.adjust')
            @foreach ($standings as $row)
                <div class="modal modal-blur fade" id="standing-adjustment-{{ $row['team_id'] }}" tabindex="-1" aria-labelledby="standing-adjustment-title-{{ $row['team_id'] }}" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <form class="modal-content" method="POST" action="{{ route('standings.adjustments.store') }}">
                            @csrf
                            <input type="hidden" name="tournament_id" value="{{ $selectedTournament->id }}">
                            <input type="hidden" name="category_id" value="{{ $selectedGroup['category_id'] }}">
                            <input type="hidden" name="series" value="{{ $selectedGroup['series'] }}">
                            <input type="hidden" name="team_id" value="{{ $row['team_id'] }}">
                            <div class="modal-header">
                                <h2 class="modal-title" id="standing-adjustment-title-{{ $row['team_id'] }}">Resolucion · {{ $row['team_name'] }}</h2>
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label" for="points-adjustment-{{ $row['team_id'] }}">Puntos a favor o en contra</label>
                                    <input class="form-control" id="points-adjustment-{{ $row['team_id'] }}" name="points_adjustment" type="number" min="-99" max="99" placeholder="Ej. -3 o 2" required>
                                    <div class="form-hint">Usa valores negativos para quitar puntos y positivos para sumar.</div>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label" for="reason-{{ $row['team_id'] }}">Motivo</label>
                                    <textarea class="form-control" id="reason-{{ $row['team_id'] }}" name="reason" rows="4" minlength="5" maxlength="1000" required></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary" type="submit">
                                    <i class="ti ti-device-floppy me-1"></i>
                                    Guardar resolucion
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endcan

        <x-ui.table-card title="Resoluciones administrativas" class="mt-3">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th class="text-center" style="width: 7rem;">Puntos</th>
                        <th>Motivo</th>
                        <th style="width: 10rem;">Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="fw-semibold">{{ $adjustment->team?->name ?? '-' }}</td>
                            <td class="text-center fw-bold {{ $adjustment->points_adjustment < 0 ? 'text-danger' : 'text-success' }}">
                                {{ $adjustment->points_adjustment > 0 ? '+' : '' }}{{ $adjustment->points_adjustment }}
                            </td>
                            <td>
                                {{ $adjustment->reason }}
                                @if ($adjustment->creator)
                                    <div class="text-body-secondary small">Registrado por {{ $adjustment->creator->name }}</div>
                                @endif
                            </td>
                            <td>{{ $adjustment->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="4" message="No hay resoluciones administrativas registradas para esta categoria y serie." />
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>

        <x-ui.table-card title="Goleadores" class="mt-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 4rem;">Pos</th>
                            <th>Jugador</th>
                            <th>Equipo</th>
                            <th class="text-center fs-3" style="width: 6rem;">Goles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topScorers as $scorer)
                            <tr>
                                <td class="text-center">
                                    <span class="badge text-bg-{{ $loop->iteration === 1 ? 'primary' : 'light border text-body' }}">
                                        {{ $loop->iteration }}
                                    </span>
                                </td>
                                <td class="fw-semibold">{{ $scorer['player_name'] }}</td>
                                <td>{{ $scorer['team_name'] }}</td>
                                <td class="text-center fs-3 fw-bold text-success">{{ $scorer['goals'] }}</td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="4" message="Aun no hay goles registrados para esta categoria y serie." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>
    @endif
    </div>
@endsection
