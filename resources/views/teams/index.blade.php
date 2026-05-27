@extends('layouts.admin')

@section('title', 'Equipos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Equipos')
@section('page-subtitle', 'Registro de equipos por liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de equipos" data-refresh-container>
        <x-slot:actions>
            <div class="d-flex gap-2">
                @can('teams.approve-updates')
                    <a class="btn btn-outline-warning btn-sm" href="{{ route('teams.approvals') }}">Aprobaciones</a>
                @endcan
                @can('teams.create')
                    <a class="btn btn-primary btn-sm" href="{{ route('teams.create') }}" data-modal-url="{{ route('teams.create') }}" data-modal-title="Nuevo equipo">Nuevo equipo</a>
                @endcan
            </div>
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Fundacion</th>
                    <th>Liga deportiva</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($teams as $team)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $team->name }}</div>
                            <div class="text-body-secondary small">{{ $team->notes ?: '-' }}</div>
                            @if ($team->pendingUpdateRequest)
                                <span class="badge text-bg-warning mt-1">Edicion pendiente</span>
                            @endif
                        </td>
                        <td>{{ $team->founded_at?->format('Y-m-d') }}</td>
                        <td>{{ $team->company?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $team->is_active ? 'success' : 'secondary' }}">{{ $team->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('teams.show', $team) }}" data-modal-url="{{ route('teams.show', $team) }}" data-modal-title="Detalle de equipo">Ver</a>
                            @can('teams.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('teams.edit', $team) }}" data-modal-url="{{ route('teams.edit', $team) }}" data-modal-title="Editar equipo">Editar</a>
                            @endcan
                            @can('teams.delete')
                                <form class="d-inline" method="POST" action="{{ route('teams.destroy', $team) }}" data-confirm-delete="Eliminar equipo?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay equipos registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $teams->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
