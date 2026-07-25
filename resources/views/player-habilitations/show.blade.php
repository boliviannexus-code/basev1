@extends('layouts.admin')

@section('title', 'Habilitar jugadores | '.config('app.name', 'Base Admin'))
@section('page-title', 'Habilitar jugadores')
@section('page-subtitle', $team->name.' · '.$tournament->name)

@section('content')
    <div data-refresh-container>
        @php
            $enabledPlayerIds = $enabledPlayers->pluck('player_id')->all();
            $pendingTransferPlayerIds = \App\Models\PlayerTransferRequest::query()
                ->where('company_id', $tournament->company_id)
                ->where('division_id', $tournament->division_id)
                ->where('status', \App\Models\PlayerTransferRequest::STATUS_PENDING)
                ->whereNull('deleted_at')
                ->pluck('player_id')
                ->all();
            $refreshUrl = route('player-habilitations.show', [
                'tournament' => $tournament,
                'team' => $team,
                'q' => $search ?: null,
            ]);
        @endphp

        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="fw-semibold">{{ $tournament->name }}</div>
                <div class="text-body-secondary small">
                    {{ $team->name }} · {{ $registration?->category?->name ?? '-' }} · {{ $registration?->seriesLabel() ?? 'Unica' }}
                </div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary btn-sm" href="{{ route('player-habilitations.teams', $tournament) }}">Equipos</a>
                @can('player-habilitations.create')
                    <a class="btn btn-primary btn-sm" href="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-modal-url="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-modal-title="Afiliar jugador al equipo">
                        Afiliar jugador
                    </a>
                @endcan
            </div>
        </div>

        <form
            class="row g-2 align-items-end mb-3"
            method="GET"
            action="{{ route('player-habilitations.show', ['tournament' => $tournament, 'team' => $team]) }}"
            data-habilitation-player-search
            data-player-lookup-url="{{ route('player-habilitations.player-lookup') }}"
            data-affiliate-form-url="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}"
            data-tournament-id="{{ $tournament->id }}"
            data-team-id="{{ $team->id }}"
        >
            <div class="col-md-10">
                <label class="form-label" for="habilitation-search">Buscar jugador en plantilla</label>
                <input class="form-control" id="habilitation-search" name="q" value="{{ $search }}" placeholder="CI, nombre, apellido o codigo" data-habilitation-search-input>
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-primary" type="submit">Buscar</button>
            </div>
            <div class="col-md-12 d-none" data-habilitation-player-summary></div>
            @can('player-habilitations.create')
                <div class="col-md-12 d-flex justify-content-end gap-2 d-none" data-habilitation-search-actions>
                    <button class="btn btn-outline-warning btn-sm d-none" type="button" data-transfer-request-button disabled>Solicitar pase</button>
                    <button class="btn btn-primary btn-sm d-none" type="submit" form="habilitation-search-enable-form" data-enable-found-player-button>Habilitar</button>
                    <a class="btn btn-primary btn-sm d-none" href="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-register-player-button data-modal-url="{{ route('player-habilitations.affiliate.form', ['tournament_id' => $tournament->id, 'team_id' => $team->id]) }}" data-modal-title="Afiliar jugador al equipo">Registrar nuevo</a>
                </div>
            @endcan
        </form>
        @can('player-habilitations.create')
            <form id="habilitation-search-enable-form" method="POST" action="{{ route('player-habilitations.enable') }}" class="d-none" data-ajax-form data-refresh-url="{{ $refreshUrl }}">
                @csrf
                <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                <input type="hidden" name="team_id" value="{{ $team->id }}">
                <input type="hidden" name="team_player_id" value="" data-enable-found-player-id>
                <input type="hidden" name="q" value="{{ $search }}" data-enable-found-player-query>
            </form>
        @endcan

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="border rounded bg-body">
                    <div class="d-flex justify-content-between align-items-center gap-2 p-3 border-bottom">
                        <h3 class="h4 mb-0">Plantilla del club</h3>
                        <span class="badge text-bg-secondary">{{ $rosterPlayers->count() }}</span>
                    </div>
                    <div class="table-responsive">
                        <table
                            class="table table-hover align-middle mb-0"
                            data-datatable
                            data-order='[[0,"asc"]]'
                            data-page-length="10"
                        >
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <th>Edad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rosterPlayers as $teamPlayer)
                                    @php
                                        $player = $teamPlayer->player;
                                        $age = $player?->age();
                                        $ageOk = $age !== null && $age >= $tournament->division->min_age && $age <= $tournament->division->max_age;
                                        $alreadyEnabled = in_array($teamPlayer->player_id, $enabledPlayerIds, true);
                                        $hasPendingTransfer = in_array($teamPlayer->player_id, $pendingTransferPlayerIds, true);
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
                                                <span class="badge text-bg-{{ $hasPendingTransfer ? 'warning' : 'secondary' }}">{{ $hasPendingTransfer ? 'Pase pendiente' : 'Pendiente' }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @can('player-habilitations.create')
                                                <form method="POST" action="{{ route('player-habilitations.enable') }}" data-ajax-form data-refresh-url="{{ $refreshUrl }}">
                                                    @csrf
                                                    <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
                                                    <input type="hidden" name="team_player_id" value="{{ $teamPlayer->id }}">
                                                    <input type="hidden" name="team_id" value="{{ $team->id }}">
                                                    <input type="hidden" name="q" value="{{ $search }}">
                                                    <button class="btn btn-outline-primary btn-sm" type="submit" @disabled($alreadyEnabled || ! $ageOk || $hasPendingTransfer)>Habilitar</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
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
                        <table
                            class="table table-hover align-middle mb-0"
                            data-datatable
                            data-order='[[0,"asc"]]'
                            data-page-length="10"
                        >
                            <thead>
                                <tr>
                                    <th>Jugador</th>
                                    <th>Habilitado</th>
                                    <th class="text-end">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($enabledPlayers as $habilitation)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $habilitation->player?->full_name ?? '-' }}</div>
                                            <div class="text-body-secondary small">CI {{ $habilitation->player?->ci ?? '-' }} · {{ $habilitation->player?->internal_code ?: 'Sin codigo' }}</div>
                                        </td>
                                        <td>{{ $habilitation->enabled_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('players.show', $habilitation->player) }}" data-modal-url="{{ route('players.show', $habilitation->player) }}" data-modal-title="Detalle de jugador">Ver</a>
                                            @can('player-habilitations.delete')
                                                <form class="d-inline" method="POST" action="{{ route('player-habilitations.destroy', $habilitation) }}" data-ajax-form data-refresh-url="{{ $refreshUrl }}" data-confirm-delete="Quitar habilitacion?" data-confirm-button-text="Si, quitar">
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
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
