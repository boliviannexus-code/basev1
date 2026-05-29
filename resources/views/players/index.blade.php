@extends('layouts.admin')

@section('title', 'Jugadores | '.config('app.name', 'Base Admin'))
@section('page-title', 'Jugadores')
@section('page-subtitle', 'Registro universal de jugadores por CI')

@section('content')
    <x-ui.table-card title="Listado de jugadores" data-refresh-container>
        <x-slot:actions>
            @can('players.create')
                <a class="btn btn-primary btn-sm" href="{{ route('players.create') }}" data-modal-url="{{ route('players.create') }}" data-modal-title="Nuevo jugador">Nuevo jugador</a>
            @endcan
        </x-slot:actions>



        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.players') }}"
            data-order='[[0,"asc"]]'
            data-columns-id="players-table-columns"
            data-filters-form="#players-filters"
        >
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>CI</th>
                    <th>Codigo</th>
                    <th>Nacimiento</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="players-table-columns">
            [
                {"data":"full_name","name":"players.last_name"},
                {"data":"ci","name":"players.ci_normalized"},
                {"data":"internal_code","name":"players.internal_code","defaultContent":"-"},
                {"data":"birth_date","name":"players.birth_date"},
                {"data":"is_active","name":"players.is_active","orderable":false,"searchable":false},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
