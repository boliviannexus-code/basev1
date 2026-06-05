@extends('layouts.admin')

@section('title', ($space->name ?: $space->title ?: 'Alojamiento').' | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: $space->title ?: 'Alojamiento')
@section('page-subtitle', $space->spaceMode?->name.' / '.($space->sharedSpaceType?->name ?: $space->privateSpaceType?->name))

@section('content')
    @error('space')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-7">
            <x-ui.card title="Datos generales">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Modalidad</dt>
                        <dd class="col-sm-8">{{ $space->spaceMode?->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Tipo</dt>
                        <dd class="col-sm-8">{{ $space->sharedSpaceType?->name ?: $space->privateSpaceType?->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">{{ ['draft' => 'Borrador', 'completed' => 'Terminado', 'needs_corrections' => 'Con correcciones', 'in_review' => 'En revision', 'approved' => 'Aprobado', 'active' => 'Habilitado', 'inactive' => 'Inactivo'][$space->status] ?? $space->status }}</dd>
                        <dt class="col-sm-4">Capacidad</dt>
                        <dd class="col-sm-8">{{ $space->max_capacity ?: 'Pendiente' }}</dd>
                        <dt class="col-sm-4">Progreso</dt>
                        <dd class="col-sm-8">
                            {{ $completion['completed_steps'] }}/{{ $completion['total_steps'] }} completado
                            @if ($completion['missing_steps'])
                                <div class="text-body-secondary small">Falta: {{ implode(', ', $completion['missing_steps']) }}</div>
                            @endif
                        </dd>
                        <dt class="col-sm-4">Descripcion corta</dt>
                        <dd class="col-sm-8">{{ $space->short_description ?: '-' }}</dd>
                        <dt class="col-sm-4">Descripcion extendida</dt>
                        <dd class="col-sm-8">{{ $space->full_description ?: '-' }}</dd>
                    </dl>
                </div>
            </x-ui.card>

            <x-ui.card title="Servicios generales" class="mt-3">
                <div class="card-body">
                    @forelse ($space->generalServices as $service)
                        <span class="badge text-bg-primary me-1 mb-1">{{ $service->name }}</span>
                    @empty
                        <span class="text-body-secondary">Sin servicios seleccionados.</span>
                    @endforelse
                </div>
            </x-ui.card>

            @include('spaces.partials.review-notes')

            @if ($space->spaceMode?->slug === 'compartido')
                <x-ui.card title="Habitaciones" class="mt-3">
                    <div class="card-body p-0">
                        <table class="table mb-0 align-middle">
                            <thead><tr><th>Habitacion</th><th>Baño</th><th>Camas</th><th>Servicios</th><th>Fotos</th></tr></thead>
                            <tbody>
                                @forelse ($space->rooms as $room)
                                    <tr>
                                        <td><div class="fw-semibold">{{ $room->title }}</div><div class="text-body-secondary small">{{ $room->name ?: $room->room_number }}</div></td>
                                        <td>{{ $room->bathroomType?->name }}</td>
                                        <td>
                                            @foreach ($room->beds as $bed)
                                                <span class="badge text-bg-light">{{ $bed->quantity }} {{ $bed->bedType?->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>
                                            @foreach ($room->roomServices as $service)
                                                <span class="badge text-bg-secondary">{{ $service->name }}</span>
                                            @endforeach
                                        </td>
                                        <td>{{ $room->photos->count() }}</td>
                                    </tr>
                                @empty
                                    <x-ui.empty-row colspan="5" message="Sin habitaciones." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif
        </div>
        <div class="col-lg-5">
            <x-ui.card title="Fotos">
                <div class="card-body">
                    @if ($space->photos->isNotEmpty())
                        <div class="space-photo-grid">
                            @foreach ($space->photos as $photo)
                                <img class="space-photo-thumb" src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt_text ?: ($space->name ?: $space->title) }}">
                            @endforeach
                        </div>
                    @else
                        <span class="text-body-secondary">Sin fotos.</span>
                    @endif
                </div>
            </x-ui.card>
            <x-ui.card title="Ubicacion" class="mt-3">
                <div class="card-body">
                    @if ($space->location)
                        <div class="fw-semibold">{{ $space->location->address }}</div>
                        <div class="text-body-secondary">{{ trim(($space->location->zone_or_neighborhood ?: '').' / '.$space->location->city.' / '.$space->location->country, ' /') }}</div>
                        @if ($space->location->reference)
                            <div class="mt-2">{{ $space->location->reference }}</div>
                        @endif
                    @else
                        <span class="text-body-secondary">Ubicacion pendiente.</span>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.index') }}">Volver</a>
        <div class="btn-list">
            @can('spaces.edit')
                @if (! $space->isApprovedLocked())
                    <a class="btn btn-outline-info" href="{{ route('spaces.continue', $space) }}">Continuar registro</a>
                @endif
                @if ($space->status === 'active')
                    <form method="POST" action="{{ route('spaces.deactivate', $space) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-outline-warning" type="submit">Deshabilitar</button>
                    </form>
                @elseif ($completion['missing_steps'] === [] && $space->approved_at !== null && in_array($space->status, ['approved', 'inactive'], true))
                    <form method="POST" action="{{ route('spaces.activate', $space) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-success" type="submit">Habilitar</button>
                    </form>
                @endif
            @endcan
        </div>
    </div>
@endsection
