@extends('layouts.admin')

@section('title', 'Jornadas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Jornadas')
@section('page-subtitle', 'Selecciona una gestion activa para administrar sus jornadas')

@section('content')
    <x-ui.table-card title="Gestiones activas">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Gestion</th>
                    <th>Liga deportiva</th>
                    <th>Jornadas</th>
                    <th>Estado</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($seasons as $season)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $season->name }}</div>
                            <div class="text-body-secondary small">{{ $season->year ?: '-' }}</div>
                        </td>
                        <td>{{ $season->company?->name ?? '-' }}</td>
                        <td>{{ $season->matchdays_count }}</td>
                        <td><span class="badge text-bg-success">Activa</span></td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm" href="{{ route('matchdays.show', $season) }}">
                                <i class="ti ti-arrow-right me-1"></i>
                                Ver jornadas
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay gestiones activas para configurar jornadas." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
