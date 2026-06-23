@extends('layouts.admin')

@section('title', 'Empresas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Empresas')
@section('page-subtitle', 'Datos base para reportes y asignacion de usuarios')

@section('content')
    @php($canManageCompanyOnlineStatus = \App\Support\CompanyContext::isGlobalAdmin(auth()->user()))
    @php($canManageCompaniesGlobally = \App\Support\CompanyContext::isGlobalAdmin(auth()->user()))

    <x-ui.table-card title="Listado de empresas" data-refresh-container>
        <x-slot:actions>
            @if ($canManageCompaniesGlobally && auth()->user()?->can('companies.create'))
                <a class="btn btn-primary btn-sm" href="{{ route('companies.create') }}" data-modal-url="{{ route('companies.create') }}" data-modal-title="Nueva empresa">Nueva empresa</a>
            @endif
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Nombre</th>
                    <th>NIT/Documento</th>
                    <th>Contacto</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    @if ($canManageCompanyOnlineStatus)
                        <th>Estado online</th>
                    @endif
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td>
                            @if ($company->logo_url)
                                <img class="avatar" src="{{ $company->logo_url }}" alt="{{ $company->name }}">
                            @else
                                <span class="avatar bg-primary-lt text-primary"><i class="ti ti-building"></i></span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $company->name }}</div>
                            <div class="text-body-secondary small">{{ $company->legal_name ?: '-' }}</div>
                        </td>
                        <td>{{ $company->tax_id ?: '-' }}</td>
                        <td>
                            <div>{{ $company->phone ?: '-' }}</div>
                            <div class="text-body-secondary small">{{ $company->email ?: '-' }}</div>
                        </td>
                        <td>{{ $company->users_count }}</td>
                        <td><span class="badge text-bg-{{ $company->is_active ? 'success' : 'secondary' }}">{{ $company->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        @if ($canManageCompanyOnlineStatus)
                            <td>
                                <span class="badge text-bg-{{ $company->is_online_enabled_by_admin ? 'success' : 'secondary' }}">
                                    {{ $company->is_online_enabled_by_admin ? 'Habilitada online' : 'No habilitada online' }}
                                </span>
                            </td>
                        @endif
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('companies.show', $company) }}" data-modal-url="{{ route('companies.show', $company) }}" data-modal-title="Detalle de empresa">Ver</a>
                            @can('companies.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('companies.edit', $company) }}" data-modal-url="{{ route('companies.edit', $company) }}" data-modal-title="Editar empresa">Editar</a>
                            @endcan
                            @if ($canManageCompanyOnlineStatus)
                                @if ($company->is_online_enabled_by_admin)
                                    <form class="d-inline" method="POST" action="{{ route('admin.companies.disable-online', $company) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-warning btn-sm" type="submit">Deshabilitar online</button>
                                    </form>
                                @else
                                    <form class="d-inline" method="POST" action="{{ route('admin.companies.enable-online', $company) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-success btn-sm" type="submit">Habilitar online</button>
                                    </form>
                                @endif
                            @endif
                            @if ($canManageCompaniesGlobally && auth()->user()?->can('companies.delete'))
                                <form class="d-inline" method="POST" action="{{ route('companies.destroy', $company) }}" data-confirm-delete="Eliminar empresa? Los usuarios asignados quedaran sin empresa.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row :colspan="$canManageCompanyOnlineStatus ? 8 : 7" message="No hay empresas registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $companies->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
