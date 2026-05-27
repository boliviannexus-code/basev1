@extends('layouts.admin')

@section('title', 'Gestiones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Gestiones')
@section('page-subtitle', 'Gestiones deportivas por liga')

@section('content')
    <x-ui.table-card title="Listado de gestiones" data-refresh-container>
        <x-slot:actions>
            @can('seasons.create')
                <a class="btn btn-primary btn-sm" href="{{ route('seasons.create') }}" data-modal-url="{{ route('seasons.create') }}" data-modal-title="Nueva gestion">Nueva gestion</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Gestion</th>
                    <th>Liga deportiva</th>
                    <th>Torneos</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
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
                        <td>{{ $season->tournaments_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ sports_status_tone($season->status) }}">{{ sports_status_label($season->status) }}</span>
                            <span class="badge text-bg-{{ $season->is_active ? 'success' : 'secondary' }}">{{ $season->is_active ? 'Activo' : 'Inactivo' }}</span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('seasons.show', $season) }}" data-modal-url="{{ route('seasons.show', $season) }}" data-modal-title="Detalle de gestion">Ver</a>
                            @can('seasons.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('seasons.edit', $season) }}" data-modal-url="{{ route('seasons.edit', $season) }}" data-modal-title="Editar gestion">Editar</a>
                            @endcan
                            @can('seasons.delete')
                                <form class="d-inline" method="POST" action="{{ route('seasons.destroy', $season) }}" data-confirm-delete="Eliminar gestion? Tambien se eliminaran sus torneos.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay gestiones registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $seasons->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
