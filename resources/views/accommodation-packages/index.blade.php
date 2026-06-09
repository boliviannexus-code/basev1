@extends('layouts.admin')

@section('title', 'Paquetes todo incluido | '.config('app.name', 'Base Admin'))
@section('page-title', 'Paquetes todo incluido')
@section('page-subtitle', 'Paquetes comerciales asociados a espacios privados')

@section('content')
    <x-ui.table-card title="Paquetes" data-refresh-container>
        <x-slot:actions>
            @can('spaces.edit')
                <a class="btn btn-primary btn-sm" href="{{ route('accommodation-packages.create') }}">
                    <i class="ti ti-plus me-1"></i>Nuevo paquete
                </a>
            @endcan
        </x-slot:actions>

        @can('spaces.edit')
            <form id="accommodation-packages-sort-form" method="post" action="{{ route('accommodation-packages.sort') }}">
                @csrf
            </form>
        @endcan

        <div>
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th>Paquete</th>
                        <th>Precio</th>
                        <th>Personas</th>
                        <th>Noches</th>
                        <th>Espacios</th>
                        <th>Servicios</th>
                        <th>Estado</th>
                        <th style="width: 110px;">Orden</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $package->name }}</div>
                                {{-- <div class="text-body-secondary small">{{ str($package->short_description)->limit(90) }}</div> --}}
                                {{-- @if (! empty($package->badges))
                                    <div class="mt-1 d-flex flex-wrap gap-1">
                                        @foreach (collect($package->badges)->take(3) as $badge)
                                            <span class="badge text-bg-light">{{ $badge }}</span>
                                        @endforeach
                                    </div>
                                @endif --}}
                                @if ($package->is_featured)
                                    <span class="badge text-bg-info mt-1">Destacado</span>
                                @endif
                            </td>
                            <td>
                                <div>{{ money_format_decimal($package->price) }} {{ $package->currency }}</div>
                                @if ($package->price_display_text)
                                    {{-- <div class="text-body-secondary small">{{ $package->price_display_text }}</div> --}}
                                @endif
                            </td>
                            <td>
                                <div>Incluye {{ $package->included_people }}</div>
                                <div class="text-body-secondary small">Max. {{ $package->max_people ?: 'sin limite' }}</div>
                            </td>
                            <td>{{ $package->nights_included }}</td>
                            <td>{{ $package->spaces_count }}</td>
                            <td>{{ $package->services_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $package->is_active ? 'success' : 'secondary' }}">{{ $package->is_active ? 'Activo' : 'Inactivo' }}</span>
                            </td>
                            <td>
                                @can('spaces.edit')
                                    <input class="form-control form-control-sm" form="accommodation-packages-sort-form" name="orders[{{ $package->id }}]" type="number" min="0" value="{{ $package->sort_order }}">
                                @else
                                    {{ $package->sort_order }}
                                @endcan
                            </td>
                            <td class="text-end">
                                @can('spaces.edit')
                                    <a class="btn btn-outline-primary btn-sm" href="{{ route('accommodation-packages.edit', $package) }}">
                                        <i class="ti ti-edit me-1"></i>Editar
                                    </a>
                                    <form class="d-inline" method="post" action="{{ route('accommodation-packages.copy', $package) }}"
                                        data-confirm-submit="Copiar paquete?"
                                        data-confirm-text="Se creara una copia inactiva para editarla como un paquete nuevo."
                                        data-confirm-button="Si, copiar">
                                        @csrf
                                        <button class="btn btn-outline-secondary btn-sm" type="submit">
                                            <i class="ti ti-copy me-1"></i>Copiar
                                        </button>
                                    </form>
                                    <form class="d-inline" method="post" action="{{ route('accommodation-packages.feature', $package) }}">
                                        @csrf
                                        @method('patch')
                                        <button class="btn btn-outline-info btn-sm" type="submit">
                                            <i class="ti ti-star me-1"></i>{{ $package->is_featured ? 'Quitar' : 'Destacar' }}
                                        </button>
                                    </form>
                                    <form class="d-inline" method="post" action="{{ route('accommodation-packages.toggle', $package) }}">
                                        @csrf
                                        @method('patch')
                                        <button class="btn btn-outline-{{ $package->is_active ? 'warning' : 'success' }} btn-sm" type="submit">
                                            <i class="ti ti-power me-1"></i>{{ $package->is_active ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    @unless ($package->is_active)
                                        <form class="d-inline" method="post" action="{{ route('accommodation-packages.destroy', $package) }}" data-confirm-delete="Eliminar paquete inactivo?">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-outline-danger btn-sm" type="submit">
                                                <i class="ti ti-trash me-1"></i>Eliminar
                                            </button>
                                        </form>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="9" message="No hay paquetes todo incluido registrados." />
                    @endforelse
                </tbody>
            </table>

            @can('spaces.edit')
                @if ($packages->isNotEmpty())
                    <div class="card-footer text-end">
                        <button class="btn btn-outline-primary btn-sm" form="accommodation-packages-sort-form" type="submit">
                            <i class="ti ti-arrows-sort me-1"></i>Guardar orden
                        </button>
                    </div>
                @endif
            @endcan
        </div>

        <x-slot:footer>{{ $packages->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
