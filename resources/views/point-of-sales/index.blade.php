@extends('layouts.admin')

@section('title', 'Puntos de venta | Inventario POS')
@section('page-title', 'Puntos de venta')
@section('page-subtitle', 'Puntos operativos separados por empresa')

@section('content')
    <x-ui.table-card title="Listado de puntos de venta" data-refresh-container>
        <x-slot:actions>
            @if (auth()->user()?->can('point-of-sales.create') || auth()->user()?->can('occupancy.manage'))
                <a class="btn btn-primary btn-sm" href="{{ route('point-of-sales.create') }}" data-modal-url="{{ route('point-of-sales.create') }}" data-modal-title="Nuevo punto de venta" data-modal-size="xl">Nuevo punto de venta</a>
            @endif
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Siguiente comprobante</th>
                    <th>Nombre</th>
                    <th>Empresa</th>
                    <th>Sucursal</th>
                    <th>Almacen vinculado</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($pointOfSales as $pointOfSale)
                    <tr>
                        <td><span class="badge text-bg-light">{{ $pointOfSale->code }}</span></td>
                        <td>
                            <span class="badge text-bg-light">
                                {{ $pointOfSale->receipt_prefix ?: $pointOfSale->code }}-{{ str_pad((string) $pointOfSale->receipt_next_number, (int) ($pointOfSale->receipt_digits ?: 6), '0', STR_PAD_LEFT) }}
                            </span>
                        </td>
                        <td>{{ $pointOfSale->name }}</td>
                        <td>{{ $pointOfSale->company?->name ?? 'Sin empresa' }}</td>
                        <td>{{ $pointOfSale->branch?->name ?? 'Sin sucursal' }}</td>
                        <td>{{ $pointOfSale->warehouse?->name ?? 'Sin almacen' }}</td>
                        <td>
                            @forelse ($pointOfSale->users as $user)
                                <span class="badge text-bg-light">{{ $user->name }}</span>
                            @empty
                                <span class="text-body-secondary">Sin usuarios</span>
                            @endforelse
                        </td>
                        <td><span class="badge text-bg-{{ $pointOfSale->is_active ? 'success' : 'secondary' }}">{{ $pointOfSale->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('point-of-sales.show', $pointOfSale) }}" data-modal-url="{{ route('point-of-sales.show', $pointOfSale) }}" data-modal-title="Detalle de punto de venta">Ver</a>
                            @if (auth()->user()?->can('point-of-sales.update') || auth()->user()?->can('occupancy.manage'))
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('point-of-sales.edit', $pointOfSale) }}" data-modal-url="{{ route('point-of-sales.edit', $pointOfSale) }}" data-modal-title="Editar punto de venta" data-modal-size="xl">Editar</a>
                            @endif
                            @can('point-of-sales.delete')
                                <form class="d-inline" method="POST" action="{{ route('point-of-sales.destroy', $pointOfSale) }}" data-confirm-delete="Eliminar punto de venta?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="9" message="No hay puntos de venta registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $pointOfSales->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
