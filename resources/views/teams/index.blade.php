@extends('layouts.admin')

@section('title', 'Equipos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Equipos')
@section('page-subtitle', 'Registro de equipos por liga deportiva')

@section('content')
    <x-ui.table-card title="Listado de equipos" data-refresh-container>
        <x-slot:actions>
            <div class="d-flex gap-2">
                @can('teams.approve-updates')
                    <a class="btn btn-outline-warning btn-sm" href="{{ route('teams.approvals') }}">Aprobaciones</a>
                @endcan
                @can('teams.create')
                    <a class="btn btn-primary btn-sm" href="{{ route('teams.create') }}" data-modal-url="{{ route('teams.create') }}" data-modal-title="Nuevo equipo">Nuevo equipo</a>
                @endcan
            </div>
        </x-slot:actions>


        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.teams') }}"
            data-order='[[0,"asc"]]'
            data-columns-id="teams-table-columns"
            data-filters-form="#teams-filters"
        >
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Fundacion</th>
                    <th>Liga deportiva</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="teams-table-columns">
            [
                {"data":"team_name","name":"teams.name"},
                {"data":"founded_at","name":"teams.founded_at"},
                {"data":"company_name","name":"companies.name","defaultContent":"-"},
                {"data":"is_active","name":"teams.is_active","orderable":false,"searchable":false},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
