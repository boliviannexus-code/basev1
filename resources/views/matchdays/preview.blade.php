@extends('layouts.admin')

@section('title', 'Vista previa jornada | '.config('app.name', 'Base Admin'))
@section('page-title', 'Vista previa del rol')
@section('page-subtitle', ($matchday->name ?? 'Jornada '.$matchday->number).' · '.$season->name)

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
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('matchdays.configure', $matchday) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Configurar jornada
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-{{ $matchday->status === 'finalized' ? 'success' : 'secondary' }}">
                {{ $matchday->status === 'finalized' ? 'Finalizada' : 'Borrador' }}
            </span>
        </div>
    </div>

    @forelse ($dates as $date)
        <x-ui.table-card title="{{ ucfirst($date->date->copy()->locale('es')->translatedFormat('l d/m/Y')) }} · {{ $date->court?->name ?? 'Sin cancha' }}">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <span class="text-body-secondary small text-uppercase fw-semibold">Fiscales</span>
                @forelse ($date->fiscals as $fiscal)
                    <span class="badge text-bg-light border text-body">
                        {{ \Carbon\Carbon::parse($fiscal->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($fiscal->end_time)->format('H:i') }}
                        · {{ $fiscal->team?->name ?? '-' }}
                    </span>
                @empty
                    <span class="badge text-bg-light border text-body">Sin fiscales asignados</span>
                @endforelse
            </div>

            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th style="width: 7rem;">Hora</th>
                        <th>Partido</th>
                        <th>Categoria</th>
                        <th>Fiscal</th>
                        <th>Torneo</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($date->fixtureMatches as $match)
                        <tr>
                            <td class="fw-semibold">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</td>
                            <td>
                                <span class="fw-semibold">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</span>
                                <span class="text-body-secondary mx-1">vs</span>
                                <span class="fw-semibold">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</span>
                                <div class="text-body-secondary small">
                                    {{ $roundLabel($match) }}
                                    @if ($match->series)
                                        · {{ \App\Models\TournamentRegistration::SERIES[$match->series] ?? $match->series }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $match->category?->name ?? '-' }}</td>
                            @php($fiscal = $match->getRelation('fiscalAssignment'))
                            <td>
                                <span class="fw-semibold">{{ $fiscal?->team?->name ?? '-' }}</span>
                                @if ($fiscal)
                                    <div class="text-body-secondary small">
                                        {{ \Carbon\Carbon::parse($fiscal->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($fiscal->end_time)->format('H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $match->tournament?->name ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="5" message="No hay partidos programados en esta fecha." />
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @empty
        <x-ui.table-card title="Rol de partidos">
            <div class="text-body-secondary">Aun no hay fechas configuradas en esta jornada.</div>
        </x-ui.table-card>
    @endforelse
@endsection
