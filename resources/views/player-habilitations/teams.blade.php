@extends('layouts.admin')

@section('title', 'Equipos del torneo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Equipos inscritos')
@section('page-subtitle', $tournament->name)

@section('content')
    <div data-refresh-container>
        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="fw-semibold">{{ $tournament->name }}</div>
                <div class="text-body-secondary small">{{ $tournament->season?->name ?? '-' }} · {{ $tournament->division?->name ?? '-' }} · {{ $tournament->categories->pluck('name')->implode(', ') ?: '-' }}</div>
            </div>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('player-habilitations.index') }}">Volver</a>
        </div>

        <x-ui.table-card title="Equipos inscritos en el torneo">
            <table
                class="table table-hover align-middle"
                data-datatable
                data-order='[[0,"asc"]]'
                data-page-length="10"
            >
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Categoria</th>
                        <th>Serie</th>
                        <th>En planilla</th>
                        <th>Habilitados</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($teams as $team)
                        @php
                            $registration = $team->registrations->first();
                        @endphp
                        <tr>
                            <td class="fw-semibold">{{ $team->name }}</td>
                            <td>{{ $registration?->category?->name ?? '-' }}</td>
                            <td>{{ $registration?->seriesLabel() ?? 'Unica' }}</td>
                            <td><span class="badge text-bg-secondary">{{ $team->roster_players_count ?? 0 }}</span></td>
                            <td><span class="badge text-bg-success">{{ $enabledCounts->get($team->id, 0) }}</span></td>
                            <td class="text-end">
                                <a class="btn btn-primary btn-sm" href="{{ route('player-habilitations.show', ['tournament' => $tournament, 'team' => $team]) }}">Habilitar jugadores</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.table-card>
    </div>
@endsection
