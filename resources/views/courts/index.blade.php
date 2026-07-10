@extends('layouts.admin')

@section('title', 'Canchas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Canchas')
@section('page-subtitle', 'Canchas registradas por liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de canchas" data-refresh-container>
        <x-slot:actions>
            @can('courts.create')
                <a class="btn btn-primary btn-sm" href="{{ route('courts.create') }}" data-modal-url="{{ route('courts.create') }}" data-modal-title="Nueva cancha">
                    Nueva cancha
                </a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Cancha</th>
                    <th>Liga deportiva</th>
                    <th>Fechas vinculadas</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($courts as $court)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $court->name }}</div>
                            <div class="text-body-secondary small">{{ $court->address ?: 'Sin direccion registrada' }}</div>
                        </td>
                        <td>{{ $court->company?->name ?? '-' }}</td>
                        <td>{{ $court->matchday_dates_count }}</td>
                        <td>
                            <span class="badge text-bg-{{ $court->is_active ? 'success' : 'secondary' }}">
                                {{ $court->is_active ? 'Activa' : 'Inactiva' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('courts.show', $court) }}" data-modal-url="{{ route('courts.show', $court) }}" data-modal-title="Detalle de cancha">Ver</a>
                            @can('courts.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('courts.edit', $court) }}" data-modal-url="{{ route('courts.edit', $court) }}" data-modal-title="Editar cancha">Editar</a>
                            @endcan
                            @can('courts.delete')
                                <form class="d-inline" method="POST" action="{{ route('courts.destroy', $court) }}" data-confirm-delete="Eliminar cancha?" data-confirm-button-text="Si, eliminar" data-confirm-text="Solo se puede eliminar si no esta vinculada a jornadas.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay canchas registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $courts->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
