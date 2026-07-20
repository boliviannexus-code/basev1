@extends('layouts.admin')

@section('title', 'Partidos de jornada | '.config('app.name', 'Base Admin'))
@section('page-title', $matchday->name ?? 'Jornada '.$matchday->number)
@section('page-subtitle', 'Registro inicial de partidos · '.$matchday->season?->name)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('match-reports.index') }}">
            <i class="ti ti-arrow-left me-1"></i>
            Jornadas finalizadas
        </a>
        <span class="badge text-bg-success">Finalizada</span>
    </div>

    @foreach ($matchday->dates as $date)
        <x-ui.table-card title="{{ ucfirst($date->date->copy()->locale('es')->translatedFormat('l d/m/Y')) }} · {{ $date->court?->name ?? 'Sin cancha' }}">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 7rem;">Hora</th>
                        <th>Partido</th>
                        <th>Categoria</th>
                        <th class="text-center" style="width: 8rem;">Registro</th>
                        <th class="text-end" style="width: 13rem;">Accion</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($date->fixtureMatches as $match)
                        <tr>
                            <td class="fw-semibold">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-nowrap">
                                    <span class="fw-semibold text-truncate" style="min-width: 0;">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</span>
                                    <span class="text-body-secondary flex-shrink-0">vs</span>
                                    <span class="fw-semibold text-truncate" style="min-width: 0;">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</span>
                                </div>
                                <div class="text-body-secondary small">{{ $match->tournament?->name ?? '-' }}</div>
                            </td>
                            <td>{{ $match->category?->name ?? '-' }}</td>
                            <td class="text-center">
                                @if ($match->report?->status === 'started')
                                    <span class="badge text-bg-primary">Iniciado</span>
                                @elseif ($match->report?->status === 'completed')
                                    <span class="badge text-bg-success">Finalizado</span>
                                @elseif ($match->report?->status === 'walkover')
                                    <span class="badge text-bg-warning">W.O.</span>
                                    <div class="text-body-secondary small">
                                        {{ $match->report->wo_reason === 'court_fee' ? 'Derecho de cancha' : 'Inasistencia' }}
                                    </div>
                                @elseif ($match->report)
                                    <span class="badge text-bg-success">Registrado</span>
                                @else
                                    <span class="badge text-bg-secondary">Pendiente</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if ($match->report?->status === 'started')
                                    <a class="btn btn-primary btn-sm" href="{{ route('match-reports.matches.play', $match) }}">
                                        Continuar
                                    </a>
                                @elseif (in_array($match->report?->status, ['completed', 'walkover'], true))
                                    <div class="d-inline-flex justify-content-end gap-1">
                                        <a class="btn btn-success btn-sm" href="{{ route('match-reports.reports.pdf', $match->report) }}" target="_blank">
                                            <i class="ti ti-printer me-1"></i>
                                            Imprimir
                                        </a>
                                        @can('match-reports.reopen')
                                            <form method="POST" action="{{ route('match-reports.reopen', $match->report) }}" data-confirm-reopen-match>
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-danger btn-sm" type="submit">
                                                    <i class="ti ti-edit me-1"></i>
                                                    Editar
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                @else
                                    <a class="btn btn-primary btn-sm" href="{{ route('match-reports.matches.edit', $match) }}">
                                        Registrar
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="5" message="No hay partidos registrados en esta fecha." />
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @endforeach
@endsection
