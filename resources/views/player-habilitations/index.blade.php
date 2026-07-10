@extends('layouts.admin')

@section('title', 'Habilitaciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Habilitaciones')
@section('page-subtitle', 'Selecciona un torneo activo para habilitar jugadores')

@section('content')
    <x-ui.table-card title="Torneos activos" data-refresh-container>
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Torneo</th>
                    <th>Gestion</th>
                    <th>Division</th>
                    <th>Categorias</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tournaments as $tournament)
                    <tr>
                        <td class="fw-semibold">{{ $tournament->name }}</td>
                        <td>{{ $tournament->season?->name ?? '-' }}</td>
                        <td>{{ $tournament->division?->name ?? '-' }}</td>
                        <td>{{ $tournament->categories->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm" href="{{ route('player-habilitations.teams', $tournament) }}">Seleccionar</a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay torneos activos para habilitar jugadores." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
