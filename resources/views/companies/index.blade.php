@extends('layouts.admin')

@section('title', 'Ligas deportivas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Ligas deportivas')
@section('page-subtitle', 'Datos base para reportes y asignacion de usuarios')

@section('content')
    <x-ui.table-card title="Listado de ligas deportivas" data-refresh-container>
        <x-slot:actions>
            @can('companies.create')
                <a class="btn btn-primary btn-sm" href="{{ route('companies.create') }}" data-modal-url="{{ route('companies.create') }}" data-modal-title="Nueva liga deportiva">Nueva liga</a>
            @endcan
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Logo</th>
                    <th>Liga deportiva</th>
                    <th>Contacto</th>
                    <th>Usuarios</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($companies as $company)
                    <tr>
                        <td>
                            @if ($company->logo_display_url)
                                <img class="avatar" src="{{ $company->logo_display_url }}" alt="{{ $company->name }}">
                            @else
                                <span class="avatar bg-primary-lt text-primary"><i class="ti ti-building"></i></span>
                            @endif
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $company->name }}</div>
                            <div class="text-body-secondary small">{{ $company->code ?: 'Sin codigo' }} · {{ trim(($company->city ?: '').' / '.($company->country ?: ''), ' /') ?: '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $company->phone ?: '-' }}</div>
                            <div class="text-body-secondary small">{{ $company->email ?: '-' }}</div>
                        </td>
                        <td>{{ $company->users_count }}</td>
                        <td><span class="badge text-bg-{{ $company->is_active ? 'success' : 'secondary' }}">{{ $company->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('companies.show', $company) }}" data-modal-url="{{ route('companies.show', $company) }}" data-modal-title="Detalle de liga deportiva">Ver</a>
                            @can('companies.update')
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('companies.edit', $company) }}" data-modal-url="{{ route('companies.edit', $company) }}" data-modal-title="Editar liga deportiva">Editar</a>
                            @endcan
                            @can('companies.delete')
                                <form class="d-inline" method="POST" action="{{ route('companies.destroy', $company) }}" data-confirm-delete="Eliminar liga deportiva? Los usuarios asignados quedaran sin liga.">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay ligas deportivas registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $companies->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
