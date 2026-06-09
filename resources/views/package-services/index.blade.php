@extends('layouts.admin')

@section('title', 'Servicios de paquetes | '.config('app.name', 'Base Admin'))
@section('page-title', 'Servicios de paquetes')
@section('page-subtitle', 'Catalogo configurable para paquetes todo incluido')

@section('content')
    <x-ui.table-card title="Servicios" data-refresh-container>
        <x-slot:actions>
            <form class="d-inline-flex gap-2" method="get" action="{{ route('package-services.index') }}">
                <select class="form-select form-select-sm" name="type" onchange="this.form.submit()">
                    <option value="">Todos los tipos</option>
                    @foreach ($types as $serviceType)
                        <option value="{{ $serviceType }}" @selected($type === $serviceType)>{{ str($serviceType)->headline() }}</option>
                    @endforeach
                </select>
            </form>
            @can('spaces.edit')
                <a class="btn btn-primary btn-sm" href="{{ route('package-services.create') }}">
                    <i class="ti ti-plus me-1"></i>Nuevo servicio
                </a>
            @endcan
        </x-slot:actions>

        @can('spaces.edit')
            <form id="package-services-sort-form" method="post" action="{{ route('package-services.sort') }}">
                @csrf
            </form>
        @endcan

        <div>
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th>Tipo</th>
                        <th>Icono</th>
                        <th>Estado</th>
                        <th style="width: 110px;">Orden</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($services as $service)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $service->name }}</div>
                                <div class="text-body-secondary small">{{ str($service->description ?: '-')->limit(90) }}</div>
                            </td>
                            <td>{{ $service->type ? str($service->type)->headline() : '-' }}</td>
                            <td>{{ $service->icon ?: '-' }}</td>
                            <td>
                                <span class="badge text-bg-{{ $service->is_active ? 'success' : 'secondary' }}">{{ $service->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td>
                                @can('spaces.edit')
                                    <input class="form-control form-control-sm" form="package-services-sort-form" name="orders[{{ $service->id }}]" type="number" min="0" value="{{ $service->sort_order }}">
                                @else
                                    {{ $service->sort_order }}
                                @endcan
                            </td>
                            <td class="text-end">
                                @can('spaces.edit')
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('package-services.edit', $service) }}">
                                        <i class="ti ti-edit me-1"></i>Editar
                                    </a>
                                    <form class="d-inline" method="post" action="{{ route('package-services.toggle', $service) }}">
                                        @csrf
                                        @method('patch')
                                        <button class="btn btn-outline-{{ $service->is_active ? 'warning' : 'success' }} btn-sm" type="submit">
                                            <i class="ti ti-power me-1"></i>{{ $service->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="6" message="No hay servicios de paquetes registrados." />
                    @endforelse
                </tbody>
            </table>

            @can('spaces.edit')
                @if ($services->isNotEmpty())
                    <div class="card-footer text-end">
                        <button class="btn btn-outline-primary btn-sm" form="package-services-sort-form" type="submit">
                            <i class="ti ti-arrows-sort me-1"></i>Guardar orden
                        </button>
                    </div>
                @endif
            @endcan
        </div>

        <x-slot:footer>{{ $services->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
