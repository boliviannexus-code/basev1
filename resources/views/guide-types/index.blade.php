@extends('layouts.admin')

@section('title', 'Tipos de guia | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tipos de guia')
@section('page-subtitle', 'Catalogo compartido para todas las empresas')

@section('content')
    <x-ui.table-card title="Listado de tipos de guia" data-refresh-container>
        <x-slot:actions>
            @can('guide_types.create')
                <a
                    class="btn btn-primary btn-sm"
                    href="{{ route('guide-types.create') }}"
                    data-modal-url="{{ route('guide-types.create') }}"
                    data-modal-title="Nuevo tipo de guia"
                >
                    Nuevo tipo
                </a>
            @endcan
        </x-slot:actions>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.guide-types') }}"
            data-order='[[0,"desc"]]'
            data-columns-id="guide-types-table-columns"
        >
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titulo</th>
                    <th>Descripcion</th>
                    <th>Creado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="guide-types-table-columns">
            [
                {"data":"id","name":"guide_types.id"},
                {"data":"title","name":"guide_types.title"},
                {"data":"description","name":"guide_types.description","defaultContent":"-"},
                {"data":"created_at","name":"guide_types.created_at"},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
