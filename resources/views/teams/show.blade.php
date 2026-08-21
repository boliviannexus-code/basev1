@extends('layouts.admin')

@section('title', $team->name.' | '.config('app.name', 'Base Admin'))
@section('page-title', $team->name)
@section('page-subtitle', 'Ficha completa del equipo')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('teams.index') }}">
            <i class="ti ti-arrow-left me-1"></i>Equipos
        </a>
        @can('teams.update')
            <a class="btn btn-primary btn-sm" href="{{ route('teams.edit', $team) }}" data-modal-url="{{ route('teams.edit', $team) }}" data-modal-title="Editar equipo">
                <i class="ti ti-edit me-1"></i>Editar equipo
            </a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-body-secondary small text-uppercase fw-semibold">Equipo</div>
                    <h2 class="mb-1">{{ $team->name }}</h2>
                    <div class="text-body-secondary">{{ $team->company?->name ?? '-' }} · Fundación: {{ $team->founded_at?->format('d/m/Y') ?? 'Sin registrar' }}</div>
                    @if ($team->notes)<p class="mb-0 mt-2">{{ $team->notes }}</p>@endif
                </div>
                <div class="d-flex gap-2">
                    <span class="badge text-bg-{{ $team->is_active ? 'success' : 'secondary' }}">{{ $team->is_active ? 'Activo' : 'Inactivo' }}</span>
                    @if ($team->pendingUpdateRequest)<span class="badge text-bg-warning">Edición pendiente</span>@endif
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        @foreach ([
            ['label' => 'Jugadores activos', 'value' => $summary['players'], 'tone' => 'primary'],
            ['label' => 'Torneos inscritos', 'value' => $summary['tournaments'], 'tone' => 'info'],
            ['label' => 'Partidos jugados', 'value' => $summary['played'], 'tone' => 'success'],
            ['label' => 'Partidos pendientes', 'value' => $summary['pending'], 'tone' => 'warning'],
        ] as $stat)
            <div class="col-6 col-lg-3">
                <div class="card h-100"><div class="card-body py-3">
                    <div class="text-body-secondary small">{{ $stat['label'] }}</div>
                    <div class="h2 mb-0 text-{{ $stat['tone'] }}">{{ $stat['value'] }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <x-ui.table-card title="Plantel del equipo">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Jugador</th><th>CI</th><th>División</th><th>Ingreso</th><th>Salida</th><th>Estado</th></tr></thead>
                <tbody>
                    @forelse ($team->teamPlayers as $membership)
                        <tr>
                            <td class="fw-semibold">{{ $membership->player?->full_name ?? '-' }}</td>
                            <td>{{ $membership->player?->ci ?? '-' }}</td>
                            <td>{{ $membership->division?->name ?? '-' }}</td>
                            <td>{{ $membership->joined_at?->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ $membership->ended_at?->format('d/m/Y') ?? '-' }}</td>
                            <td><span class="badge text-bg-{{ $membership->status === 'active' ? 'success' : 'secondary' }}">{{ $membership->status === 'active' ? 'Activo' : 'Inactivo' }}</span></td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="6" message="El equipo no tiene jugadores afiliados." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>

    <x-ui.table-card title="Inscripciones en torneos" class="mt-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Torneo</th><th>Gestión</th><th>División</th><th>Categoría</th><th>Serie</th><th>Número</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($team->registrations as $registration)
                    <tr>
                        <td class="fw-semibold">{{ $registration->tournament?->name ?? '-' }}</td>
                        <td>{{ $registration->tournament?->season?->name ?? '-' }}</td>
                        <td>{{ $registration->division?->name ?? '-' }}</td>
                        <td>{{ $registration->category?->name ?? '-' }}</td>
                        <td>{{ $registration->seriesLabel() }}</td>
                        <td>{{ $registration->team_number ?? '-' }}</td>
                        <td><span class="badge text-bg-light border text-body">{{ ucfirst($registration->status) }}</span></td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="El equipo no tiene inscripciones en torneos." />
                @endforelse
            </tbody>
        </table></div>
    </x-ui.table-card>

    <x-ui.table-card title="Habilitaciones de jugadores por torneo" class="mt-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Jugador</th><th>Torneo</th><th>Categoría</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($team->tournamentTeamPlayers as $habilitation)
                    <tr>
                        <td class="fw-semibold">{{ $habilitation->player?->full_name ?? '-' }}</td>
                        <td>{{ $habilitation->tournament?->name ?? '-' }}</td>
                        <td>{{ $habilitation->tournamentRegistration?->category?->name ?? '-' }}</td>
                        <td>{{ $habilitation->enabled_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $habilitation->status === 'enabled' ? 'success' : 'secondary' }}">{{ $habilitation->status === 'enabled' ? 'Habilitado' : 'Deshabilitado' }}</span></td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No existen habilitaciones registradas." />
                @endforelse
            </tbody>
        </table></div>
    </x-ui.table-card>

    <x-ui.table-card title="Pases de jugadores" class="mt-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Código</th><th>Jugador</th><th>Movimiento</th><th>División</th><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($transfers as $transfer)
                    <tr>
                        <td class="fw-semibold">{{ $transfer->code ?? '-' }}</td>
                        <td>{{ $transfer->player?->full_name ?? '-' }}</td>
                        <td>
                            <span class="badge text-bg-{{ (int) $transfer->to_team_id === (int) $team->id ? 'success' : 'warning' }}">
                                {{ (int) $transfer->to_team_id === (int) $team->id ? 'Entrada' : 'Salida' }}
                            </span>
                            <div class="small text-body-secondary">{{ $transfer->fromTeam?->name ?? '-' }} → {{ $transfer->toTeam?->name ?? '-' }}</div>
                        </td>
                        <td>{{ $transfer->division?->name ?? '-' }}</td>
                        <td>{{ $transfer->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ player_transfer_status_label($transfer->status) }}</td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No existen pases relacionados con este equipo." />
                @endforelse
            </tbody>
        </table></div>
    </x-ui.table-card>

    <x-ui.table-card title="Partidos y resultados" class="mt-3">
        <x-slot:actions>
            <div class="small text-body-secondary">Goles: {{ $summary['goals'] }} · Amarillas: {{ $summary['yellow_cards'] }} · Rojas: {{ $summary['red_cards'] }}</div>
        </x-slot:actions>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Torneo</th><th>Etapa</th><th>Partido</th><th class="text-center">Resultado</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($matches as $match)
                    @php $finished = in_array($match->report?->status, ['completed', 'walkover'], true); @endphp
                    <tr>
                        <td>
                            <div>{{ $match->matchdayDate?->date?->format('d/m/Y') ?? 'Por programar' }}</div>
                            <div class="small text-body-secondary">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }} · {{ $match->matchdayDate?->court?->name ?? 'Sin cancha' }}</div>
                        </td>
                        <td>{{ $match->tournament?->name ?? '-' }}<div class="small text-body-secondary">{{ $match->category?->name ?? '-' }}</div></td>
                        <td>{{ $match->round_number ? 'Fecha '.$match->round_number : $match->stage }}</td>
                        <td><span class="fw-semibold">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</span> vs <span class="fw-semibold">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</span></td>
                        <td class="text-center fw-bold">{{ $finished ? $match->report->home_score.' - '.$match->report->away_score : '-' }}</td>
                        <td><span class="badge text-bg-{{ $finished ? 'success' : ($match->matchday_date_id ? 'info' : 'secondary') }}">{{ $finished ? ($match->report->status === 'walkover' ? 'Finalizado W.O.' : 'Finalizado') : ($match->matchday_date_id ? 'Programado' : 'Pendiente') }}</span></td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="El equipo no tiene partidos generados." />
                @endforelse
            </tbody>
        </table></div>
    </x-ui.table-card>
@endsection
