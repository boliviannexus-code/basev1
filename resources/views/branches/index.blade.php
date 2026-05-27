@extends('layouts.admin')

@section('title', 'Sucursales | Inventario POS')
@section('page-title', 'Sucursales')
@section('page-subtitle', 'Administracion de puntos de operacion')

@section('content')
    <x-ui.table-card title="Listado de sucursales" data-refresh-container>
        <x-slot:actions>
            @can('branches.create')
                <a class="btn btn-primary btn-sm" href="{{ route('branches.create') }}" data-modal-url="{{ route('branches.create') }}" data-modal-title="Nueva sucursal">Nueva sucursal</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Liga deportiva</th>
                    <th>Telefono</th>
                    <th>Almacenes</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($branches as $branch)
                    <tr>
                        <td><span class="badge text-bg-light">{{ $branch->code }}</span></td>
                        <td>{{ $branch->name }}</td>
                        <td>{{ $branch->company?->name ?? 'Sin liga deportiva' }}</td>
                        <td>{{ $branch->phone ?: '-' }}</td>
                        <td>{{ $branch->warehouses_count }}</td>
                        <td><span class="badge text-bg-{{ $branch->is_active ? 'success' : 'secondary' }}">{{ $branch->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('branches.show', $branch) }}" data-modal-url="{{ route('branches.show', $branch) }}" data-modal-title="Detalle de sucursal">Ver</a>
                            @can('branches.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('branches.edit', $branch) }}" data-modal-url="{{ route('branches.edit', $branch) }}" data-modal-title="Editar sucursal">Editar</a>
                            @endcan
                            @can('branches.delete')
                                <form class="d-inline" method="POST" action="{{ route('branches.destroy', $branch) }}" data-confirm-delete="Eliminar sucursal? Tambien se eliminaran sus almacenes.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="No hay sucursales registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $branches->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
