@extends('layouts.admin')

@section('title', 'Fixture generado | '.config('app.name', 'Base Admin'))
@section('page-title', 'Fixture generado')
@section('page-subtitle', ($generation->tournament?->name ?? '-').' · '.($generation->category?->name ?? '-'))

@section('content')
    @php
        $roundLabel = function ($match): string {
            $label = $match->round_number
                ? 'Fecha '.$match->round_number
                : ($match->tie_number ? $match->stage.' · Llave '.$match->tie_number : $match->stage);

            return $label.($match->leg_number > 1 ? ' · Vuelta' : '');
        };
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('fixtures.configure', ['tournament' => $generation->tournament, 'category' => $generation->category]) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Configuracion
        </a>
        <div class="d-flex gap-2">
            @if ($hasSecondPhaseSeeds && ! $seedsResolved && $firstPhasePendingCount === 0)
                <form method="POST" action="{{ route('fixtures.resolve-seeds', $generation) }}" data-confirm-resolve-seeds>
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-primary btn-sm" type="submit">
                        <i class="ti ti-arrows-sort me-1"></i>
                        Resolver semillas
                    </button>
                </form>
            @endif
            <a class="btn btn-success btn-sm" href="{{ route('fixtures.report.pdf', $generation) }}" target="_blank" rel="noopener">
                <i class="ti ti-file-type-pdf me-1"></i>
                Imprimir fixture completo
            </a>
            <a class="btn btn-outline-success btn-sm" href="{{ route('fixtures.report.teams-pdf', $generation) }}" target="_blank" rel="noopener">
                <i class="ti ti-users me-1"></i>
                Imprimir por equipo
            </a>
            @can('fixtures.generate')
                <form method="POST" action="{{ route('fixtures.destroy', $generation) }}"
                    data-confirm-delete="¿Eliminar todo el fixture?"
                    data-confirm-button-text="Sí, eliminar todo"
                    data-confirm-text="ÚLTIMO RECURSO: se borrarán permanentemente todos los partidos de este fixture, sus programaciones en jornadas y fechas, planillas, resultados y sanciones, aunque ya hayan sido jugados. Esta acción no se puede deshacer."
                    data-confirm-color="#dc2626">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-danger btn-sm" type="submit">
                        <i class="ti ti-trash me-1"></i>
                        Eliminar todo el fixture
                    </button>
                </form>
            @endcan
        </div>
    </div>

    @if ($hasSecondPhaseSeeds && ! $seedsResolved && $firstPhasePendingCount > 0)
        <div class="alert alert-warning">
            Para resolver las semillas de segunda fase faltan {{ $firstPhasePendingCount }} partido(s) de primera fase por finalizar.
        </div>
    @elseif ($seedsResolved)
        <div class="alert alert-success">
            Las semillas de segunda fase ya fueron resueltas con la tabla final de posiciones.
        </div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="text-body-secondary small">Primera fase</div>
                    <div class="fw-semibold">{{ ((int) ($generation->config['first_phase_rounds'] ?? 1)) === 2 ? 'Ida y vuelta' : 'Solo ida' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="text-body-secondary small">Clasificacion</div>
                    <div class="fw-semibold">{{ isset($generation->config['qualifiers_per_series']) ? $generation->config['qualifiers_per_series'].' por serie' : 'Por definir' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="text-body-secondary small">Segunda fase</div>
                    <div class="fw-semibold">{{ ['knockout' => 'Llaves', 'league' => 'Liguilla', 'accumulative' => 'Acumulativo'][$generation->config['second_phase_mode'] ?? ''] ?? 'Por definir' }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body py-3">
                    <div class="text-body-secondary small">Partidos</div>
                    <div class="fw-semibold">{{ $generation->matches_count }}</div>
                </div>
            </div>
        </div>
    </div>

    @foreach ($matchesByStage as $stageKey => $matches)
        @php
            [$phase, $stage] = explode('|', $stageKey, 2);
        @endphp
        <x-ui.table-card title="{{ $stage }}">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="text-body-secondary small">{{ ['group' => 'Primera fase', 'knockout' => 'Llaves', 'league' => 'Liguilla', 'league_playoff' => 'Definicion de liguilla'][$phase] ?? $phase }}</div>
                <span class="badge text-bg-light border text-body">{{ $matches->count() }} partidos</span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Ronda</th>
                            <th>Serie</th>
                            <th>Local / referencia</th>
                            <th class="text-center">Resultado</th>
                            <th>Visitante / referencia</th>
                            <th>Programacion</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($matches as $match)
                            @php
                                $status = match ($match->report?->status) {
                                    'completed' => ['label' => 'Finalizado', 'tone' => 'success'],
                                    'walkover' => ['label' => 'W.O.', 'tone' => 'warning'],
                                    'started' => ['label' => 'Iniciado', 'tone' => 'primary'],
                                    default => $match->matchday_date_id
                                        ? ['label' => 'Programado', 'tone' => 'info']
                                        : ['label' => 'Pendiente de programacion', 'tone' => 'secondary'],
                                };
                            @endphp
                            <tr>
                                <td>{{ $match->match_number }}</td>
                                <td>{{ $roundLabel($match) }}</td>
                                <td>{{ $match->series ? (\App\Models\TournamentRegistration::SERIES[$match->series] ?? $match->series) : '-' }}</td>
                                <td>{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</td>
                                <td class="text-center fw-bold">
                                    @if ($match->report)
                                        {{ $match->report->home_score }} - {{ $match->report->away_score }}
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</td>
                                <td>
                                    @if ($match->matchdayDate)
                                        <div class="fw-semibold">{{ $match->matchdayDate->date?->format('d/m/Y') ?? '-' }}</div>
                                        <div class="text-body-secondary small">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : 'Sin hora' }}</div>
                                    @else
                                        <span class="text-body-secondary">Sin fecha</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $status['tone'] }}">{{ $status['label'] }}</span>
                                    @if ($match->report?->status === 'walkover')
                                        <div class="text-body-secondary small">{{ $match->report->wo_reason === 'court_fee' ? 'Derecho de cancha' : 'Inasistencia' }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>
    @endforeach
@endsection
