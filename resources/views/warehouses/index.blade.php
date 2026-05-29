@extends('layouts.admin')

@section('title', 'Almacenes | Inventario POS')
@section('page-title', 'Almacenes')
@section('page-subtitle', 'Administracion de almacenes por sucursal')

@section('content')
    <x-ui.table-card title="Listado de almacenes" data-refresh-container>
        <x-slot:actions>
            @can('warehouses.create')
                <a class="btn btn-primary btn-sm" href="{{ route('warehouses.create') }}" data-modal-url="{{ route('warehouses.create') }}" data-modal-title="Nuevo almacen">Nuevo almacen</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Nombre</th>
                    <th>Liga deportiva</th>
                    <th>Sucursal</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($warehouses as $warehouse)
                    <tr>
                        <td><span class="badge text-bg-light">{{ $warehouse->code }}</span></td>
                        <td>{{ $warehouse->name }}</td>
                        <td>{{ $warehouse->company?->name ?? 'Sin liga deportiva' }}</td>
                        <td>{{ $warehouse->branch?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $warehouse->is_active ? 'success' : 'secondary' }}">{{ $warehouse->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('warehouses.show', $warehouse) }}" data-modal-url="{{ route('warehouses.show', $warehouse) }}" data-modal-title="Detalle de almacen">Ver</a>
                            @can('warehouses.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('warehouses.edit', $warehouse) }}" data-modal-url="{{ route('warehouses.edit', $warehouse) }}" data-modal-title="Editar almacen">Editar</a>
                            @endcan
                            @can('warehouses.delete')
                                <form class="d-inline" method="POST" action="{{ route('warehouses.destroy', $warehouse) }}" data-confirm-delete="Eliminar almacen?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay almacenes registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $warehouses->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
