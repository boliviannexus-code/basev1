@extends('layouts.admin')

@section('title', 'Alojamientos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Alojamientos')
@section('page-subtitle', 'Privados y compartidos registrados por tu empresa')

@section('content')
    @error('space')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <x-ui.table-card title="Listado principal de alojamientos">
        <x-slot:actions>
            @can('spaces.create')
                <div class="btn-list">
                    <a class="btn btn-primary btn-sm" href="{{ route('spaces.private.create') }}"><i class="ti ti-home-plus me-1"></i>Nuevo Espacio</a>
                        </div>
            @endcan
        </x-slot:actions>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.spaces') }}"
            data-order='[[7,"desc"]]'
            data-columns-id="spaces-table-columns"
        >
            <thead>
                <tr>
                    <th>Alojamiento</th>
                    <th>Modalidad</th>
                    <th>Tipo</th>
                    <th>Ubicacion</th>
                    <th>Capacidad</th>
                    <th>Estado</th>
                    <th>Progreso</th>
                    <th>Actualizado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="spaces-table-columns">
            [
                {"data":"display_name","name":"display_name"},
                {"data":"mode_name","name":"mode_name","orderable":false,"searchable":false},
                {"data":"type_name","name":"type_name","orderable":false,"searchable":false},
                {"data":"location_label","name":"location_label","orderable":false,"searchable":false},
                {"data":"capacity_label","name":"spaces.max_capacity","searchable":false},
                {"data":"status","name":"spaces.status"},
                {"data":"completion","name":"completion","orderable":false,"searchable":false},
                {"data":"updated_at","name":"spaces.updated_at"},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
