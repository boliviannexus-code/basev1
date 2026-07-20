@extends('layouts.admin')

@section('title', 'Partidos para tarjetas rojas | '.config('app.name', 'Base Admin'))
@section('page-title', $matchday->name)
@section('page-subtitle', 'Partidos de la jornada para registrar tarjetas rojas')

@section('content')
    <div class="mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('red-cards.index') }}">
            <i class="ti ti-arrow-left me-1"></i>
            Jornadas
        </a>
    </div>

    @foreach ($matchday->dates as $date)
        <x-ui.table-card title="{{ $date->date?->format('d/m/Y') ?? 'Fecha sin dia' }} · {{ $date->court?->name ?? 'Sin cancha' }}" class="mb-3">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Partido</th>
                        <th>Categoria</th>
                        <th>Rojas</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($date->fixtureMatches as $match)
                        <tr>
                            <td>{{ $match->scheduled_time ?: '-' }}</td>
                            <td>
                                <div class="d-flex align-items-center gap-2 flex-nowrap">
                                    <span class="fw-semibold text-truncate" style="min-width: 0;">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Equipo A' }}</span>
                                    <span class="text-body-secondary flex-shrink-0">vs</span>
                                    <span class="fw-semibold text-truncate" style="min-width: 0;">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Equipo B' }}</span>
                                </div>
                            </td>
                            <td>{{ $match->category?->name ?? '-' }}</td>
                            <td><span class="badge text-bg-danger">{{ $match->red_card_sanctions_count }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-primary btn-sm" href="{{ route('red-cards.matches.edit', $match) }}">Registrar rojas</a>
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="5" message="No hay partidos programados en esta fecha." />
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @endforeach
@endsection
