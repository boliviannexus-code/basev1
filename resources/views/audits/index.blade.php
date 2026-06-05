@extends('layouts.admin')

@section('title', 'Auditoria | '.config('app.name', 'Base Admin'))
@section('page-title', 'Auditoria')
@section('page-subtitle', 'Registro de acciones y cambios del sistema')

@section('content')
    <x-ui.table-card title="Auditoria de acciones">
        <form class="stock-filter-bar" id="audit-filters" autocomplete="off" data-datatable-filters>
            @if ($companies->count() > 1)
                <div>
                    <label class="form-label" for="audit-filter-company">Liga deportiva</label>
                    <select class="form-select form-select-sm" id="audit-filter-company" name="company_id" data-tom-select data-placeholder="Todas">
                        <option value="">Todas</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div>
                <label class="form-label" for="audit-filter-user">Usuario</label>
                <select class="form-select form-select-sm" id="audit-filter-user" name="user_id" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="audit-filter-event">Accion</label>
                <select class="form-select form-select-sm" id="audit-filter-event" name="event">
                    <option value="">Todas</option>
                    <option value="created">Creado</option>
                    <option value="updated">Editado</option>
                    <option value="deleted">Eliminado</option>
                    <option value="restored">Restaurado</option>
                </select>
            </div>

            <div>
                <label class="form-label" for="audit-filter-module">Modulo</label>
                <select class="form-select form-select-sm" id="audit-filter-module" name="auditable_type" data-tom-select data-placeholder="Todos">
                    <option value="">Todos</option>
                    @foreach ($auditableTypes as $type => $label)
                        <option value="{{ $type }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="form-label" for="audit-filter-date-from">Desde</label>
                <input class="form-control form-control-sm" id="audit-filter-date-from" name="date_from" type="date">
            </div>

            <div>
                <label class="form-label" for="audit-filter-date-to">Hasta</label>
                <input class="form-control form-control-sm" id="audit-filter-date-to" name="date_to" type="date">
            </div>

            <button class="btn btn-outline-secondary btn-sm" type="reset">
                Limpiar
            </button>
        </form>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.audits') }}"
            data-order='[[0,"desc"]]'
            data-columns-id="audit-table-columns"
            data-filters-form="#audit-filters"
        >
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Liga deportiva</th>
                    <th>Usuario</th>
                    <th>Accion</th>
                    <th>Modulo</th>
                    <th>ID</th>
                    <th>Cambios</th>
                    <th>IP</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="audit-table-columns">
            [
                {"data":"created_at","name":"audits.created_at"},
                {"data":"company_name","name":"companies.name","defaultContent":"Global"},
                {"data":"user_name","name":"users.name","defaultContent":"Sistema"},
                {"data":"event","name":"audits.event","orderable":false},
                {"data":"auditable_label","name":"audits.auditable_type"},
                {"data":"record_id","name":"audits.auditable_id","searchable":false},
                {"data":"changes","name":"changes","orderable":false,"searchable":false},
                {"data":"ip_address","name":"audits.ip_address","defaultContent":"-"},
                {"data":"actions","name":"actions","className":"text-end","orderable":false,"searchable":false}
            ]
        </script>
    </x-ui.table-card>
@endsection
