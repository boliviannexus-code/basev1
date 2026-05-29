@extends('layouts.admin')

@section('title', 'Habilitaciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Habilitaciones')
@section('page-subtitle', 'Jugadores habilitados por torneo y equipo')

@section('content')
    <div data-refresh-container>
        <x-ui.card title="Filtro de habilitacion" class="mb-3">
            <div class="card-body">
                <form class="row g-2 align-items-end" method="GET" action="{{ route('player-habilitations.index') }}">
                    <div class="col-md-4">
                        <label class="form-label" for="habilitation-tournament">Torneo</label>
                        <select class="form-select" id="habilitation-tournament" name="tournament_id" data-tom-select data-placeholder="Seleccionar torneo" data-habilitation-autosubmit>
                            @foreach ($tournaments as $tournamentOption)
                                <option value="{{ $tournamentOption->id }}" @selected($tournament?->id === $tournamentOption->id)>
                                    {{ $tournamentOption->name }} - {{ $tournamentOption->season?->name ?? '-' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="habilitation-team">Equipo</label>
                        <select class="form-select" id="habilitation-team" name="team_id" data-tom-select data-placeholder="Seleccionar equipo">
                            @foreach ($teams as $teamOption)
                                <option value="{{ $teamOption->id }}" @selected($team?->id === $teamOption->id)>{{ $teamOption->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="habilitation-search">Buscar jugador</label>
                        <input class="form-control" id="habilitation-search" name="q" value="{{ $search }}" placeholder="CI, nombre, apellido o codigo">
                    </div>
                    <div class="col-md-1 d-grid">
                        <button class="btn btn-primary" type="submit">Ver</button>
                    </div>
                </form>
            </div>
        </x-ui.card>

        @if (! $tournament)
            <x-ui.card>
                <div class="card-body text-body-secondary">No hay torneos activos para habilitar jugadores.</div>
            </x-ui.card>
        @elseif (! $team)
            <x-ui.card>
                <div class="card-body text-body-secondary">Selecciona un equipo inscrito en el torneo.</div>
            </x-ui.card>
        @else
            @php
                $enabledPlayerIds = $enabledPlayers->pluck('player_id')->all();
            @endphp

            <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <div class="fw-semibold">{{ $tournament->name }}</div>
                    <div class="text-body-secondary small">{{ $team->name }} · {{ $tournament->division?->name ?? '-' }} · {{ $tournament->category?->name ?? '-' }}</div>
                </div>
                @can('player-habilitations.create')
                    <a class="btn btn-primary btn-sm" href="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-modal-url="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-modal-title="Afiliar jugador al equipo">
                        Afiliar jugador
                    </a>
                @endcan
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="border rounded bg-body">
                        <div class="d-flex justify-content-between align-items-center gap-2 p-3 border-bottom">
                            <h3 class="h4 mb-0">Plantilla del club</h3>
                            <span class="badge text-bg-secondary">{{ $rosterPlayers->count() }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Jugador</th>
                                        <th>Edad</th>
                                        <th>Estado</th>
                                        <th class="text-end">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($rosterPlayers as $teamPlayer)
                                        @php
                                            $player = $teamPlayer->player;
                                            $age = $player?->age();
                                            $ageOk = $age !== null && $age >= $tournament->division->min_age && $age <= $tournament->division->max_age;
                                            $alreadyEnabled = in_array($teamPlayer->player_id, $enabledPlayerIds, true);
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $player?->full_name ?? '-' }}</div>
                                                <div class="text-body-secondary small">CI {{ $player?->ci ?? '-' }} · {{ $player?->internal_code ?: 'Sin codigo' }}</div>
                                            </td>
                                            <td>
                                                {{ $age ?? '-' }}
                                                <span class="badge text-bg-{{ $ageOk ? 'success' : 'danger' }} ms-1">{{ $ageOk ? 'Valido' : 'Fuera' }}</span>
                                            </td>
                                            <td>
                                                @if ($alreadyEnabled)
                                                    <span class="badge text-bg-success">Habilitado</span>
                                                @else
                                                    <span class="badge text-bg-secondary">Pendiente</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @can('player-habilitations.create')
                                                    <form method="POST" action="{{ route('player-habilitations.enable') }}">
                                                        @csrf
                                                        <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                                                        <input type="hidden" name="team_player_id" value="{{ $teamPlayer->id }}">
                                                        <input type="hidden" name="team_id" value="{{ $team->id }}">
                                                        <input type="hidden" name="q" value="{{ $search }}">
                                                        <button class="btn btn-outline-primary btn-sm" type="submit" @disabled($alreadyEnabled || ! $ageOk)>Habilitar</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <x-ui.empty-row colspan="4" message="No hay jugadores afiliados a este club en la division del torneo." />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="border rounded bg-body">
                        <div class="d-flex justify-content-between align-items-center gap-2 p-3 border-bottom">
                            <h3 class="h4 mb-0">Habilitados al torneo</h3>
                            <span class="badge text-bg-success">{{ $enabledPlayers->count() }}</span>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Jugador</th>
                                        <th>Habilitado</th>
                                        <th class="text-end">Accion</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($enabledPlayers as $habilitation)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $habilitation->player?->full_name ?? '-' }}</div>
                                                <div class="text-body-secondary small">CI {{ $habilitation->player?->ci ?? '-' }} · {{ $habilitation->player?->internal_code ?: 'Sin codigo' }}</div>
                                            </td>
                                            <td>{{ $habilitation->enabled_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('players.show', $habilitation->player) }}" data-modal-url="{{ route('players.show', $habilitation->player) }}" data-modal-title="Detalle de jugador">Ver</a>
                                                @can('player-habilitations.delete')
                                                    <form class="d-inline" method="POST" action="{{ route('player-habilitations.destroy', $habilitation) }}" data-confirm-delete="Quitar habilitacion?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                                                        <input type="hidden" name="team_id" value="{{ $team->id }}">
                                                        <input type="hidden" name="q" value="{{ $search }}">
                                                        <button class="btn btn-outline-danger btn-sm" type="submit">Quitar</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <x-ui.empty-row colspan="3" message="No hay jugadores habilitados para este torneo." />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
