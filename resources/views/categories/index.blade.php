@extends('layouts.admin')

@section('title', 'Categorias | '.config('app.name', 'Base Admin'))
@section('page-title', 'Categorias')
@section('page-subtitle', 'Categorias reutilizables por division y liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de categorias" data-refresh-container>
        <x-slot:actions>
            @can('categories.create')
                <a class="btn btn-primary btn-sm" href="{{ route('categories.create') }}" data-modal-url="{{ route('categories.create') }}" data-modal-title="Nueva categoria">Nueva categoria</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Division</th>
                    <th>Liga deportiva</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $category->name }}</div>
                            <div class="text-body-secondary small">{{ $category->description ?: '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $category->division?->name ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $category->division?->min_age }} a {{ $category->division?->max_age }} años</div>
                        </td>
                        <td>{{ $category->company?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('categories.show', $category) }}" data-modal-url="{{ route('categories.show', $category) }}" data-modal-title="Detalle de categoria">Ver</a>
                            @can('categories.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('categories.edit', $category) }}" data-modal-url="{{ route('categories.edit', $category) }}" data-modal-title="Editar categoria">Editar</a>
                            @endcan
                            @can('categories.delete')
                                <form class="d-inline" method="POST" action="{{ route('categories.destroy', $category) }}" data-confirm-delete="Eliminar categoria?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay categorias registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $categories->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
