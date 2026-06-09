@extends('layouts.admin')

@section('title', 'Paises | '.config('app.name', 'Base Admin'))
@section('page-title', 'Paises')
@section('page-subtitle', 'Catalogo para priorizar la busqueda de paises en check-in')

@section('content')
    <div class="row g-3">
        <div class="col-md-6 col-xl-3">
            <x-ui.stat-card label="Paises activos" :value="$activeCount" icon="ti ti-world" tone="primary" />
        </div>
        <div class="col-md-6 col-xl-3">
            <x-ui.stat-card label="Destacados" :value="$featuredCount" icon="ti ti-star" tone="warning" />
        </div>
    </div>

    <x-ui.table-card class="mt-3" title="Paises registrados">
        <x-slot:actions>
            <form class="d-flex flex-column flex-md-row gap-2" method="get" action="{{ route('countries.index') }}">
                <input class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="Buscar pais o codigo">
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    <option value="featured" @selected($status === 'featured')>Destacados</option>
                    <option value="active" @selected($status === 'active')>Activos</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactivos</option>
                </select>
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="ti ti-search me-1"></i>Buscar
                </button>
            </form>
        </x-slot:actions>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Pais</th>
                    <th>Codigo</th>
                    <th>Orden</th>
                    <th>Busqueda check-in</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($countries as $country)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $country->name }}</div>
                            @if ($country->is_featured)
                                <span class="badge text-bg-warning">Destacado</span>
                            @endif
                        </td>
                        <td><span class="badge text-bg-light">{{ $country->iso_code }}</span></td>
                        <td style="width: 140px">
                            <form class="d-flex gap-2" method="post" action="{{ route('countries.update', $country) }}">
                                @csrf
                                @method('put')
                                <input class="form-control form-control-sm" name="sort_order" type="number" min="0" value="{{ $country->sort_order }}">
                                <button class="btn btn-outline-primary btn-sm" type="submit" title="Guardar orden">
                                    <i class="ti ti-device-floppy"></i>
                                </button>
                            </form>
                        </td>
                        <td>
                            <span class="text-body-secondary small">
                                {{ $country->is_featured ? 'Aparecera primero' : 'Orden normal' }}
                            </span>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $country->is_active ? 'success' : 'secondary' }}">
                                {{ $country->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <form class="d-inline" method="post" action="{{ route('countries.feature', $country) }}">
                                @csrf
                                @method('patch')
                                <button class="btn btn-outline-{{ $country->is_featured ? 'warning' : 'secondary' }} btn-sm" type="submit">
                                    <i class="ti ti-star me-1"></i>{{ $country->is_featured ? 'Quitar' : 'Destacar' }}
                                </button>
                            </form>
                            <form class="d-inline" method="post" action="{{ route('countries.toggle', $country) }}">
                                @csrf
                                @method('patch')
                                <button class="btn btn-outline-{{ $country->is_active ? 'warning' : 'success' }} btn-sm" type="submit">
                                    <i class="ti ti-power me-1"></i>{{ $country->is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="No hay paises registrados." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $countries->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
