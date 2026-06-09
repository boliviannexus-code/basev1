@extends('layouts.admin')

@section('title', 'Metodos de pago | Inventario POS')
@section('page-title', 'Metodos de pago')
@section('page-subtitle', 'Catalogo para ventas y cobros mixtos')

@section('content')
    <x-ui.table-card title="Listado de metodos" data-refresh-container>
        <x-slot:actions>
            @if (auth()->user()?->can('payment-methods.create') || auth()->user()?->can('occupancy.manage'))
                <a class="btn btn-primary btn-sm" href="{{ route('payment-methods.create') }}" data-modal-url="{{ route('payment-methods.create') }}" data-modal-title="Nuevo metodo de pago">
                    Nuevo metodo
                </a>
            @endif
        </x-slot:actions>

        <table
            class="table table-hover align-middle"
            data-datatable
            data-url="{{ route('datatables.payment-methods') }}"
            data-order='[[0,"desc"]]'
            data-columns-id="payment-methods-table-columns"
        >
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Estado</th>
                    <th>Creado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
        <script type="application/json" id="payment-methods-table-columns">
            [
                {"data":"id","name":"payment_methods.id"},
                {"data":"name","name":"payment_methods.name"},
                {"data":"is_active","name":"payment_methods.is_active","orderable":false,"searchable":false},
                {"data":"created_at","name":"payment_methods.created_at"},
                {"data":"actions","name":"actions","orderable":false,"searchable":false,"className":"text-end"}
            ]
        </script>
    </x-ui.table-card>
@endsection
