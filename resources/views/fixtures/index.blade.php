@extends('layouts.admin')

@section('title', 'Fixture | '.config('app.name', 'Base Admin'))
@section('page-title', 'Fixture')
@section('page-subtitle', 'Selecciona un torneo para preparar su configuracion')

@section('content')
    <x-ui.table-card title="Torneos disponibles">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Torneo</th>
                    <th>Gestion</th>
                    <th>Division</th>
                    <th>Categorias</th>
                    <th>Equipos inscritos</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tournaments as $tournament)
                    <tr>
                        <td class="fw-semibold">{{ $tournament->name }}</td>
                        <td>{{ $tournament->season?->name ?? '-' }}</td>
                        <td>{{ $tournament->division?->name ?? '-' }}</td>
                        <td>{{ $tournament->categories->pluck('name')->implode(', ') ?: '-' }}</td>
                        <td>{{ $tournament->registrations_count }}</td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm" href="{{ route('fixtures.categories', $tournament) }}">
                                <i class="ti ti-arrow-right me-1"></i>
                                Categorias
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay torneos activos para configurar fixture." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
