@extends('layouts.admin')

@section('title', 'Metodos de pago | Inventario POS')
@section('page-title', 'Metodos de pago')
@section('page-subtitle', 'Catalogo para ventas y cobros mixtos')

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card title="Nuevo metodo">
                <div class="card-body">
                    <form method="POST" action="{{ route('payment-methods.store') }}" novalidate>
                        @csrf
                        @include('payment-methods.partials.fields', ['paymentMethod' => $paymentMethod, 'prefix' => 'payment-method-create'])
                        <button class="btn btn-primary w-100 mt-3" type="submit">
                            <i class="ti ti-plus me-1"></i>Crear metodo
                        </button>
                    </form>
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-8">
            <x-ui.table-card title="Metodos registrados">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Metodo</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paymentMethods as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="text-body-secondary small">ID {{ $item->id }}</div>
                                    </td>
                                    <td>
                                        <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Activo' : 'Inactivo' }}</span>
                                    </td>
                                    <td>{{ $item->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                                    <td class="text-end">
                                        @if (auth()->user()?->can('payment-methods.update') || auth()->user()?->can('occupancy.manage'))
                                            <button class="btn btn-outline-primary btn-sm" type="button" data-payment-method-edit-toggle="{{ $item->id }}">
                                                <i class="ti ti-edit me-1"></i>Editar
                                            </button>
                                        @endif
                                        @can('payment-methods.delete')
                                            <form class="d-inline" method="POST" action="{{ route('payment-methods.destroy', $item) }}" data-confirm-delete="Eliminar metodo de pago?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger btn-sm" type="submit">
                                                    <i class="ti ti-trash me-1"></i>Eliminar
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                                <tr class="d-none" data-payment-method-edit-panel="{{ $item->id }}">
                                    <td colspan="4">
                                        <form method="POST" action="{{ route('payment-methods.update', $item) }}" novalidate>
                                            @csrf
                                            @method('PUT')
                                            @include('payment-methods.partials.fields', ['paymentMethod' => $item, 'prefix' => 'payment-method-'.$item->id])
                                            <div class="d-flex justify-content-end gap-2 mt-3">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-payment-method-edit-toggle="{{ $item->id }}">Cancelar</button>
                                                <button class="btn btn-primary btn-sm" type="submit">Guardar cambios</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="4" message="No hay metodos de pago registrados." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>{{ $paymentMethods->links() }}</x-slot:footer>
            </x-ui.table-card>
        </div>
    </div>
@endsection
