@extends('layouts.admin')

@section('title', 'Categorias de cargos extras | '.config('app.name', 'Base Admin'))
@section('page-title', 'Categorias de cargos extras')
@section('page-subtitle', 'Catalogo independiente por empresa')

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card title="Nueva categoria">
                <div class="card-body">
                    <form method="POST" action="{{ route('extra-charge-categories.store') }}">
                        @csrf
                        @include('extra-charge-categories.partials.fields', ['category' => $category, 'prefix' => 'extra-charge-category-create'])
                        <button class="btn btn-primary w-100 mt-3" type="submit">
                            <i class="ti ti-plus me-1"></i>Crear categoria
                        </button>
                    </form>
                </div>
            </x-ui.card>
        </div>
        <div class="col-lg-8">
            <x-ui.table-card title="Categorias registradas">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="text-end">Precio</th>
                                <th>Estado</th>
                                <th class="text-end">Uso</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categories as $item)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $item->name }}</div>
                                        <div class="text-body-secondary small">Orden {{ $item->sort_order }}{{ $item->is_protected ? ' · Base' : '' }}</div>
                                    </td>
                                    <td class="text-end">{{ money_format_decimal($item->default_unit_price) }} Bs</td>
                                    <td>
                                        <span class="badge text-bg-{{ $item->is_active ? 'success' : 'secondary' }}">{{ $item->is_active ? 'Activa' : 'Inactiva' }}</span>
                                    </td>
                                    <td class="text-end">{{ $item->account_statement_items_count + $item->reservation_extra_charges_count }}</td>
                                    <td class="text-end">
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-extra-charge-category-edit-toggle="{{ $item->id }}">
                                            <i class="ti ti-edit me-1"></i>Editar
                                        </button>
                                        <form class="d-inline" method="POST" action="{{ route('extra-charge-categories.toggle', $item) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-outline-{{ $item->is_active ? 'warning' : 'success' }} btn-sm" type="submit">
                                                <i class="ti ti-power me-1"></i>{{ $item->is_active ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                        <form class="d-inline" method="POST" action="{{ route('extra-charge-categories.destroy', $item) }}" data-confirm-delete="Eliminar categoria?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" type="submit" @disabled($item->is_protected || ($item->account_statement_items_count + $item->reservation_extra_charges_count) > 0)>
                                                <i class="ti ti-trash me-1"></i>Eliminar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <tr class="d-none" data-extra-charge-category-edit-panel="{{ $item->id }}">
                                    <td colspan="5">
                                        <form method="POST" action="{{ route('extra-charge-categories.update', $item) }}">
                                            @csrf
                                            @method('PUT')
                                            @include('extra-charge-categories.partials.fields', ['category' => $item, 'prefix' => 'extra-charge-category-'.$item->id])
                                            <div class="d-flex justify-content-end gap-2 mt-3">
                                                <button class="btn btn-outline-secondary btn-sm" type="button" data-extra-charge-category-edit-toggle="{{ $item->id }}">Cancelar</button>
                                                <button class="btn btn-primary btn-sm" type="submit">Guardar cambios</button>
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="5" message="No hay categorias registradas." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>{{ $categories->links() }}</x-slot:footer>
            </x-ui.table-card>
        </div>
    </div>
@endsection
