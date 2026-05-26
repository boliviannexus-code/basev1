@extends('layouts.admin')

@section('title', 'Tipos de transporte | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tipos de transporte')
@section('page-subtitle', 'Catalogo compartido para todas las empresas')

@section('content')
    <x-ui.table-card title="Listado de tipos de transporte" data-refresh-container>
        <x-slot:actions>
            @can('transport_types.create')
                <a
                    class="btn btn-primary btn-sm"
                    href="{{ route('transport-types.create') }}"
                    data-modal-url="{{ route('transport-types.create') }}"
                    data-modal-title="Nuevo tipo de transporte"
                >
                    Nuevo tipo
                </a>
            @endcan
        </x-slot:actions>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.transport-types') }}"
            data-order='[[0,"desc"]]'
            data-columns-id="transport-types-table-columns"
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
        <script type="application/json" id="transport-types-table-columns">
            [
                {"data":"id","name":"transport_types.id"},
                {"data":"title","name":"transport_types.title"},
                {"data":"description","name":"transport_types.description","defaultContent":"-"},
                {"data":"created_at","name":"transport_types.created_at"},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
