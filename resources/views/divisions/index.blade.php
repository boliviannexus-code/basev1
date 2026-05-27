@extends('layouts.admin')

@section('title', 'Divisiones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Divisiones')
@section('page-subtitle', 'Divisiones reutilizables por liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de divisiones" data-refresh-container>
        <x-slot:actions>
            @can('divisions.create')
                <a class="btn btn-primary btn-sm" href="{{ route('divisions.create') }}" data-modal-url="{{ route('divisions.create') }}" data-modal-title="Nueva division">Nueva division</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                    <tr>
                        <th>Division</th>
                        <th>Rango de edad</th>
                        <th>Categorias</th>
                        <th>Liga deportiva</th>
                        <th>Torneos</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
            </thead>
            <tbody>
                @forelse ($divisions as $division)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $division->name }}</div>
                            <div class="text-body-secondary small">{{ $division->description ?: '-' }}</div>
                        </td>
                        <td>{{ $division->min_age }} a {{ $division->max_age }} años</td>
                        <td>{{ $division->categories_count }}</td>
                        <td>{{ $division->company?->name ?? '-' }}</td>
                        <td>{{ $division->tournaments_count }}</td>
                        <td><span class="badge text-bg-{{ $division->is_active ? 'success' : 'secondary' }}">{{ $division->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('divisions.show', $division) }}" data-modal-url="{{ route('divisions.show', $division) }}" data-modal-title="Detalle de division">Ver</a>
                            @can('divisions.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('divisions.edit', $division) }}" data-modal-url="{{ route('divisions.edit', $division) }}" data-modal-title="Editar division">Editar</a>
                            @endcan
                            @can('divisions.delete')
                                <form class="d-inline" method="POST" action="{{ route('divisions.destroy', $division) }}" data-confirm-delete="Eliminar division?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="No hay divisiones registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $divisions->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
