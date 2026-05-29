@extends('layouts.admin')

@section('title', 'Tipos de actividad | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tipos de actividad')
@section('page-subtitle', 'Catalogo universal para construir itinerarios profesionales')

@section('content')
    <x-ui.table-card title="Listado de tipos de actividad" data-refresh-container>
        <x-slot:actions>
            @can('activity_types.create')
                <a
                    class="btn btn-primary btn-sm"
                    href="{{ route('activity-types.create') }}"
                    data-modal-url="{{ route('activity-types.create') }}"
                    data-modal-title="Nuevo tipo de actividad"
                >
                    Nuevo tipo
                </a>
            @endcan
        </x-slot:actions>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.activity-types') }}"
            data-order='[[0,"desc"]]'
            data-columns-id="activity-types-table-columns"
        >
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Titulo</th>
                    <th>Slug</th>
                    <th>Icono</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="activity-types-table-columns">
            [
                {"data":"id","name":"activity_types.id"},
                {"data":"title","name":"activity_types.title"},
                {"data":"slug","name":"activity_types.slug"},
                {"data":"icon","name":"activity_types.icon","defaultContent":"-"},
                {"data":"is_active","name":"activity_types.is_active"},
                {"data":"created_at","name":"activity_types.created_at"},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
