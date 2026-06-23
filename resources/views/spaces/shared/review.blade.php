@extends('layouts.admin')

@section('title', 'Revision | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: 'Alojamiento compartido')
@section('page-subtitle', 'Revision final')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')

    @error('space')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    @if ($missingRequirements)
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">Aun no se puede terminar el registro.</div>
            <div>Completa los siguientes datos: {{ implode(', ', $missingRequirements) }}.</div>
        </div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <x-ui.card title="Resumen">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Tipo</dt>
                        <dd class="col-sm-8">{{ $space->sharedSpaceType?->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8">{{ $space->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Capacidad calculada</dt>
                        <dd class="col-sm-8">{{ $space->max_capacity ?: 0 }} huespedes</dd>
                        <dt class="col-sm-4">Habitaciones</dt>
                        <dd class="col-sm-8">{{ $space->rooms->count() }}</dd>
                        <dt class="col-sm-4">Camas</dt>
                        <dd class="col-sm-8">{{ $space->rooms->flatMap->beds->sum('quantity') }}</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            @php($statusMap = ['draft' => ['Borrador', 'secondary'], 'completed' => ['Terminado', 'info'], 'needs_corrections' => ['Con correcciones', 'warning'], 'in_review' => ['En revision', 'purple'], 'approved' => ['Aprobado', 'primary'], 'active' => ['Habilitado', 'success'], 'inactive' => ['Inactivo', 'warning']])
                            <span class="badge text-bg-{{ $statusMap[$space->status][1] ?? 'secondary' }}">{{ $statusMap[$space->status][0] ?? $space->status }}</span>
                        </dd>
                        <dt class="col-sm-4">Descripcion corta</dt>
                        <dd class="col-sm-8">{{ $space->short_description ?: '-' }}</dd>
                        <dt class="col-sm-4">Descripcion extendida</dt>
                        <dd class="col-sm-8">{{ $space->full_description ?: '-' }}</dd>
                    </dl>
                </div>
            </x-ui.card>

            <x-ui.card title="Habitaciones" class="mt-3">
                <div class="card-body p-0">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Habitacion</th><th>Camas</th><th>Capacidad</th><th>Servicios</th></tr></thead>
                        <tbody>
                            @forelse ($space->rooms as $room)
                                <tr>
                                    <td>{{ $room->title }}</td>
                                    <td>
                                        @foreach ($room->beds as $bed)
                                            <span class="badge text-bg-light">{{ $bed->quantity }} {{ $bed->bedType?->name }}</span>
                                        @endforeach
                                    </td>
                                    <td>{{ $room->max_capacity }}</td>
                                    <td>
                                        @foreach ($room->roomServices as $service)
                                            <span class="badge text-bg-secondary">{{ $service->name }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="4" message="Sin habitaciones." />
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-ui.card>

            @include('spaces.partials.review-notes')
        </div>

        <div class="col-lg-5">
            <x-ui.card title="Servicios generales">
                <div class="card-body">
                    @forelse ($space->generalServices as $service)
                        <span class="badge text-bg-primary me-1 mb-1">{{ $service->name }}</span>
                    @empty
                        <span class="text-body-secondary">Sin servicios seleccionados.</span>
                    @endforelse
                </div>
            </x-ui.card>
            <x-ui.card title="Ubicacion" class="mt-3">
                <div class="card-body">
                    @if ($space->location)
                        <div class="fw-semibold">{{ $space->location->address }}</div>
                        <div class="text-body-secondary">{{ trim(($space->location->zone_or_neighborhood ?: '').' / '.$space->location->city.' / '.$space->location->country, ' /') }}</div>
                    @else
                        <span class="text-body-secondary">Sin ubicacion registrada.</span>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.shared.location.edit', $space) }}">Volver</a>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('spaces.shared.draft', $space) }}" data-ajax-form>
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-primary" type="submit">Guardar como borrador</button>
            </form>
            <form method="POST" action="{{ route('spaces.shared.publish', $space) }}" data-ajax-form>
                @csrf
                @method('PATCH')
                <button class="btn btn-success" type="submit" @disabled($missingRequirements !== [])>Terminar registro</button>
            </form>
        </div>
    </div>
    </div>
@endsection
