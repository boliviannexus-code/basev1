@extends('layouts.admin')

@section('title', 'Revision | '.config('app.name', 'Base Admin'))
@section('page-title', $space->title ?: 'Alojamiento privado')
@section('page-subtitle', 'Revision final')

@section('content')
    <div data-refresh-container>
    @include('spaces.private.partials.stepper')

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
                        <dd class="col-sm-8">{{ $space->privateSpaceType?->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Titulo</dt>
                        <dd class="col-sm-8">{{ $space->title ?: '-' }}</dd>
                        <dt class="col-sm-4">Capacidad</dt>
                        <dd class="col-sm-8">{{ $space->max_capacity ?: '-' }} huespedes</dd>
                        <dt class="col-sm-4">Distribucion</dt>
                        <dd class="col-sm-8">
                            {{ $space->bedrooms_count ?? 0 }} habitaciones,
                            {{ $space->beds_count ?? 0 }} camas,
                            {{ $space->private_bathrooms_count ?? 0 }} baños privados,
                            {{ $space->shared_bathrooms_count ?? 0 }} baños compartidos
                        </dd>
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

            <x-ui.card title="Servicios" class="mt-3">
                <div class="card-body">
                    @forelse ($space->generalServices as $service)
                        <span class="badge text-bg-primary me-1 mb-1">{{ $service->name }}</span>
                    @empty
                        <span class="text-body-secondary">Sin servicios seleccionados.</span>
                    @endforelse
                </div>
            </x-ui.card>

            @include('spaces.partials.review-notes')
        </div>

        <div class="col-lg-5">
            <x-ui.card title="Fotos">
                <div class="card-body">
                    @php($mainPhoto = $space->photos->firstWhere('type', 'main'))
                    @if ($mainPhoto)
                        <img class="space-photo-review-main mb-2" src="{{ Storage::disk('public')->url($mainPhoto->path) }}" alt="{{ $space->title }}">
                    @else
                        <div class="text-body-secondary mb-2">Sin foto principal.</div>
                    @endif
                    @if ($space->photos->where('type', 'gallery')->isNotEmpty())
                        <div class="space-photo-grid">
                            @foreach ($space->photos->where('type', 'gallery') as $photo)
                                <img class="space-photo-thumb" src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt_text ?: $space->title }}">
                            @endforeach
                        </div>
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
                        @if ($space->location->latitude || $space->location->longitude)
                            <div class="text-body-secondary small mt-2">{{ $space->location->latitude }}, {{ $space->location->longitude }}</div>
                        @endif
                    @else
                        <span class="text-body-secondary">Sin ubicacion registrada.</span>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>

    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.private.location.edit', $space) }}">Volver</a>
        <div class="d-flex gap-2">
            <form method="POST" action="{{ route('spaces.private.draft', $space) }}" data-ajax-form>
                @csrf
                @method('PATCH')
                <button class="btn btn-outline-primary" type="submit">Guardar como borrador</button>
            </form>
            <form method="POST" action="{{ route('spaces.private.publish', $space) }}" data-ajax-form>
                @csrf
                @method('PATCH')
                <button class="btn btn-success" type="submit" @disabled($missingRequirements !== [])>Terminar registro</button>
            </form>
        </div>
    </div>
    </div>
@endsection
