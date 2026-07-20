@extends('layouts.admin')

@section('title', 'Dashboard | Base Admin')
@section('page-title', 'Dashboard')
@section('page-subtitle', $dashboardCompany ? 'Indicadores deportivos de '.$dashboardCompany->name : 'Indicadores deportivos por liga')

@section('content')
    <div class="dashboard-hero mb-3">
        <div class="dashboard-company">
            @if ($dashboardCompany?->logo_url)
                <img class="dashboard-company-logo" src="{{ $dashboardCompany->logo_url }}" alt="{{ $dashboardCompany->name }}">
            @else
                <span class="dashboard-company-mark">{{ str($dashboardCompany?->name ?? config('app.name', 'BA'))->substr(0, 2)->upper() }}</span>
            @endif
            <div>
                <div class="text-body-secondary small">Liga activa</div>
                <h2 class="mb-1">{{ $dashboardCompany?->name ?? config('app.name', 'Base Admin') }}</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge text-bg-primary">{{ $activeSeasons }} gestion(es) activa(s)</span>
                    <span class="badge text-bg-success">{{ $activeTournaments }} torneo(s) activo(s)</span>
                    <span class="text-body-secondary small">{{ now()->format('Y-m-d H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Equipos activos" :value="$activeTeams" icon="ti ti-shield-star" tone="primary" />
            <div class="dashboard-stat-note">{{ $registeredTeams }} inscripciones en torneos</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Jugadores activos" :value="$activePlayers" icon="ti ti-user-star" tone="success" />
            <div class="dashboard-stat-note">{{ $enabledPlayers }} habilitados vigentes</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Partidos fixture" :value="$totalMatches" icon="ti ti-tournament" tone="warning" />
            <div class="dashboard-stat-note">{{ $reportedMatches }} con registro finalizado</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Goles registrados" :value="$totalGoals" icon="ti ti-ball-football" tone="info" />
            <div class="dashboard-stat-note">{{ number_format($goalsPerMatch, 2) }} goles por partido</div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-4">
            <x-ui.card title="Avance deportivo">
                <div class="card-body">
                    <div class="dashboard-bar-item">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="dashboard-bar-label">Partidos programados</span>
                            <span class="dashboard-bar-value">{{ $matchProgress['scheduled'] }} / {{ $totalMatches }}</span>
                        </div>
                        <div class="dashboard-bar-track"><span style="width: {{ $matchProgress['scheduled_percent'] }}%"></span></div>
                    </div>
                    <div class="dashboard-bar-item">
                        <div class="d-flex justify-content-between gap-2">
                            <span class="dashboard-bar-label">Registros finalizados</span>
                            <span class="dashboard-bar-value">{{ $matchProgress['reported'] }} / {{ $totalMatches }}</span>
                        </div>
                        <div class="dashboard-bar-track"><span style="width: {{ $matchProgress['reported_percent'] }}%"></span></div>
                    </div>
                    <div class="dashboard-alert-row">
                        <span class="avatar avatar-sm bg-warning-lt text-warning"><i class="ti ti-clock"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $matchProgress['pending'] }} partido(s) pendientes de registro</div>
                            <div class="text-body-secondary small">{{ $matchdaysCount }} jornada(s) creadas en la liga</div>
                        </div>
                    </div>
                    <div class="dashboard-alert-row">
                        <span class="avatar avatar-sm bg-info-lt text-info"><i class="ti ti-switch-horizontal"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $pendingTransfers }} pase(s) pendiente(s)</div>
                            <div class="text-body-secondary small">Solicitudes que requieren revision deportiva.</div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            <x-ui.table-card title="Goleadores">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jugador</th>
                            <th>Equipo</th>
                            <th class="text-end">Goles</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topScorers as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->player?->full_name ?? '-' }}</td>
                                <td>{{ $row->team?->name ?? '-' }}</td>
                                <td class="text-end"><span class="badge text-bg-primary">{{ (int) $row->goals }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin goles registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

        <div class="col-lg-4">
            <x-ui.table-card title="Torneos con mas equipos">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Torneo</th>
                            <th>Gestion</th>
                            <th class="text-end">Equipos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tournamentsByRegistrations as $tournament)
                            <tr>
                                <td class="fw-semibold">{{ $tournament->name }}</td>
                                <td>{{ $tournament->season?->name ?? '-' }}</td>
                                <td class="text-end"><span class="badge text-bg-success">{{ $tournament->registered_teams_count }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="3">Sin torneos activos con inscripciones.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12">
            <x-ui.table-card title="Equipos con mas participaciones">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Equipo</th>
                            <th class="text-end">Inscripciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($teamsByRegistration as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row->team?->name ?? '-' }}</td>
                                <td class="text-end"><span class="badge text-bg-secondary">{{ (int) $row->total }}</span></td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="2">Sin equipos inscritos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>
@endsection
