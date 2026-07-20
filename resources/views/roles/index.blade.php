@extends('layouts.admin')

@section('title', 'Roles | '.config('app.name', 'Base Admin'))
@section('page-title', 'Roles')
@section('page-subtitle', 'Administracion de perfiles y permisos')

@section('content')
    <x-ui.table-card title="Listado de roles" data-refresh-container>
        <x-slot:actions>
            @can('roles.create')
                <a class="btn btn-primary btn-sm" href="{{ route('roles.create') }}" data-modal-url="{{ route('roles.create') }}" data-modal-title="Nuevo rol">
                    <i class="ti ti-plus me-1"></i>
                    Nuevo rol
                </a>
            @endcan
        </x-slot:actions>
        <table class="table table-hover align-middle">
            <thead><tr><th>Rol</th><th>Usuarios</th><th>Permisos</th><th>Creado</th><th class="text-end">Acciones</th></tr></thead>
            <tbody>
                @forelse ($roles as $role)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ role_label($role->name) }}</div>
                            <div class="text-body-secondary small">{{ $role->name }}</div>
                        </td>
                        <td><span class="badge text-bg-light border text-body">{{ $role->users_count }}</span></td>
                        <td><span class="badge text-bg-primary-lt">{{ $role->permissions_count }} permisos</span></td>
                        <td>{{ $role->created_at?->format('Y-m-d') }}</td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('roles.show', $role) }}" data-modal-url="{{ route('roles.show', $role) }}" data-modal-title="Detalle de rol">
                                <i class="ti ti-eye me-1"></i>
                                Ver
                            </a>
                            @can('roles.edit')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('roles.edit', $role) }}" data-modal-url="{{ route('roles.edit', $role) }}" data-modal-title="Editar rol">
                                    <i class="ti ti-pencil me-1"></i>
                                    Editar
                                </a>
                            @endcan
                            @can('roles.assign-permissions')
                                <a class="btn btn-outline-info btn-sm" href="{{ route('roles.permissions.form', $role) }}" data-modal-url="{{ route('roles.permissions.form', $role) }}" data-modal-title="Asignar permisos">
                                    <i class="ti ti-shield-check me-1"></i>
                                    Permisos
                                </a>
                            @endcan
                            @can('roles.delete')
                                <form class="d-inline" method="POST" action="{{ route('roles.destroy', $role) }}" data-confirm-delete="Eliminar rol?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">
                                        <i class="ti ti-trash me-1"></i>
                                        Eliminar
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay roles registrados." />
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $roles->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
