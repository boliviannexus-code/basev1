@extends('layouts.admin')

@section('title', 'Articulos de sancion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Articulos de sancion')
@section('page-subtitle', 'Reglamento aplicado a tarjetas rojas')

@section('content')
    <x-ui.table-card title="Listado de articulos">
        <x-slot:actions>
            @can('red-card-articles.create')
                <a class="btn btn-primary btn-sm" href="{{ route('red-card-articles.create') }}">Nuevo articulo</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Articulo nro.</th>
                    <th>Detalle</th>
                    <th>Liga deportiva</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($articles as $article)
                    <tr>
                        <td class="fw-semibold">{{ $article->number }}</td>
                        <td>{{ str($article->detail)->limit(120) }}</td>
                        <td>{{ $article->company?->name ?? '-' }}</td>
                        <td class="text-end">
                            @can('red-card-articles.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('red-card-articles.edit', $article) }}">Editar</a>
                            @endcan
                            @can('red-card-articles.delete')
                                <form class="d-inline" method="POST" action="{{ route('red-card-articles.destroy', $article) }}" data-confirm-delete="Eliminar articulo?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="4" message="No hay articulos registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $articles->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
