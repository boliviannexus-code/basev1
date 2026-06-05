@extends('layouts.admin')

@section('title', 'Torneos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Torneos')
@section('page-subtitle', 'Torneos por gestion y liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de torneos" data-refresh-container>
        <x-slot:actions>
            @can('tournaments.create')
                <a class="btn btn-primary btn-sm" href="{{ route('tournaments.create') }}" data-modal-url="{{ route('tournaments.create') }}" data-modal-title="Nuevo torneo">Nuevo torneo</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Torneo</th>
                    <th>Gestion</th>
                    <th>Division</th>
                    <th>Categoria</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tournaments as $tournament)
                    <tr>
                        <td class="fw-semibold">{{ $tournament->name }}</td>
                        <td>{{ $tournament->season?->name ?? '-' }}</td>
                        <td>{{ $tournament->division?->name ?? '-' }}</td>
                        <td>{{ $tournament->category?->name ?? '-' }}</td>
                         <td>
                            <span class="badge text-bg-{{ sports_status_tone($tournament->status) }}">{{ sports_status_label($tournament->status) }}</span>
                            <span class="badge text-bg-{{ $tournament->is_active ? 'success' : 'secondary' }}">{{ $tournament->is_active ? 'Activo' : 'Inactivo' }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('tournaments.show', $tournament) }}" data-modal-url="{{ route('tournaments.show', $tournament) }}" data-modal-title="Detalle de torneo">Ver</a>
                            @can('tournaments.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('tournaments.edit', $tournament) }}" data-modal-url="{{ route('tournaments.edit', $tournament) }}" data-modal-title="Editar torneo">Editar</a>
                            @endcan
                            @can('tournaments.delete')
                                <form class="d-inline" method="POST" action="{{ route('tournaments.destroy', $tournament) }}" data-confirm-delete="Eliminar torneo?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="No hay torneos registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $tournaments->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
