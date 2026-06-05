@extends('layouts.admin')

@section('title', ($space->name ?: $space->title ?: 'Alojamiento').' | Revision')
@section('page-title', $space->name ?: $space->title ?: 'Alojamiento')
@section('page-subtitle', ($space->company?->name ?: 'Empresa').' / '.$space->spaceMode?->name)

@section('content')
    @php
        $isShared = $space->spaceMode?->slug === 'compartido';
        $statusMap = [
            'draft' => ['Borrador', 'secondary'],
            'completed' => ['Terminado', 'info'],
            'needs_corrections' => ['Con correcciones', 'warning'],
            'in_review' => ['En revision', 'purple'],
            'approved' => ['Aprobado', 'primary'],
            'active' => ['Habilitado', 'success'],
            'inactive' => ['Inactivo', 'warning'],
        ];
        $noteLabels = [
            'approval' => 'Aprobacion',
            'correction' => 'Correccion solicitada',
            'suspension' => 'Suspension temporal',
        ];
    @endphp

    @error('message')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-lg-7">
            <x-ui.card title="Datos del alojamiento">
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Empresa</dt>
                        <dd class="col-sm-8">{{ $space->company?->name ?: '-' }}</dd>
                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            <span class="badge text-bg-{{ $statusMap[$space->status][1] ?? 'secondary' }}">{{ $statusMap[$space->status][0] ?? $space->status }}</span>
                        </dd>
                        <dt class="col-sm-4">Tipo</dt>
                        <dd class="col-sm-8">{{ $isShared ? $space->sharedSpaceType?->name : $space->privateSpaceType?->name }}</dd>
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8">{{ $isShared ? $space->name : $space->title }}</dd>
                        <dt class="col-sm-4">Capacidad</dt>
                        <dd class="col-sm-8">{{ $space->max_capacity ?: 'Pendiente' }}</dd>
                        <dt class="col-sm-4">Descripcion corta</dt>
                        <dd class="col-sm-8">{{ $space->short_description ?: '-' }}</dd>
                        <dt class="col-sm-4">Descripcion extendida</dt>
                        <dd class="col-sm-8">{{ $space->full_description ?: '-' }}</dd>
                    </dl>
                </div>
            </x-ui.card>

            @if ($isShared)
                <x-ui.card title="Habitaciones" class="mt-3">
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>Habitacion</th><th>Baño</th><th>Camas</th><th>Capacidad</th><th>Fotos</th></tr></thead>
                            <tbody>
                                @forelse ($space->rooms as $room)
                                    <tr>
                                        <td>{{ $room->name ?: $room->title }}</td>
                                        <td>{{ $room->bathroomType?->name }}</td>
                                        <td>{{ $room->beds->sum('quantity') }}</td>
                                        <td>{{ $room->max_capacity ?: 0 }}</td>
                                        <td>{{ $room->photos_skipped ? 'No usa fotos' : $room->photos->count() }}</td>
                                    </tr>
                                @empty
                                    <x-ui.empty-row colspan="5" message="Sin habitaciones." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card title="Historial de revision" class="mt-3">
                <div class="card-body">
                    @forelse ($space->reviewNotes as $note)
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="fw-semibold">
                                    {{ $noteLabels[$note->type] ?? str($note->type)->replace('_', ' ')->headline() }}
                                </div>
                                <div class="text-body-secondary small">{{ $note->created_at?->format('Y-m-d H:i') }}</div>
                            </div>
                            <div class="text-body-secondary small mb-2">{{ $note->user?->name ?: 'Sistema' }}</div>
                            <div>{{ $note->message }}</div>
                        </div>
                    @empty
                        <span class="text-body-secondary">Sin observaciones todavia.</span>
                    @endforelse
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-5">
            <x-ui.card title="Fotos">
                <div class="card-body">
                    @if ($space->photos_skipped)
                        <span class="text-body-secondary">La empresa indico que no usara fotos generales.</span>
                    @elseif ($space->photos->isNotEmpty())
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
                    @else
                        <span class="text-body-secondary">Ubicacion pendiente.</span>
                    @endif
                </div>
            </x-ui.card>

            @if (in_array($space->status, ['completed', 'needs_corrections', 'approved', 'active', 'inactive'], true))
                <x-ui.card title="Decision" class="mt-3">
                    <div class="card-body">
                        @if ($space->status === 'completed')
                            <form class="mb-3" method="POST" action="{{ route('admin.spaces.approve', $space) }}">
                                @csrf
                                @method('PATCH')
                                <button class="btn btn-success w-100" type="submit">
                                    <i class="ti ti-check me-1"></i>Aprobar alojamiento
                                </button>
                            </form>
                        @endif

                        @if (in_array($space->status, ['completed', 'needs_corrections'], true))
                            <form method="POST" action="{{ route('admin.spaces.corrections', $space) }}">
                                @csrf
                                @method('PATCH')
                                <label class="form-label" for="correction-message">Observaciones / correcciones</label>
                                <textarea class="form-control @error('message') is-invalid @enderror" id="correction-message" name="message" rows="5" minlength="10" required>{{ old('message') }}</textarea>
                                <div class="invalid-feedback">{{ $errors->first('message') }}</div>
                                <button class="btn btn-warning w-100 mt-3" type="submit">
                                    <i class="ti ti-message-report me-1"></i>Enviar correcciones
                                </button>
                            </form>
                        @endif

                        @if (in_array($space->status, ['approved', 'active', 'inactive'], true))
                            <hr>
                            <form method="POST" action="{{ route('admin.spaces.suspend-review', $space) }}">
                                @csrf
                                @method('PATCH')
                                <label class="form-label" for="suspend-message">Observaciones para revision</label>
                                <textarea class="form-control @error('message') is-invalid @enderror" id="suspend-message" name="message" rows="5" minlength="10" required>{{ old('message') }}</textarea>
                                <div class="form-hint">El alojamiento dejara de estar habilitado y quedara en estado En revision.</div>
                                <div class="invalid-feedback">{{ $errors->first('message') }}</div>
                                <button class="btn btn-danger w-100 mt-3" type="submit">
                                    <i class="ti ti-ban me-1"></i>Suspender y enviar a revision
                                </button>
                            </form>
                        @endif
                    </div>
                </x-ui.card>
            @endif
        </div>
    </div>

    <div class="mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('admin.spaces.approvals') }}">Volver</a>
    </div>
@endsection
