@extends('layouts.admin')

@section('title', 'Habitaciones | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: 'Alojamiento compartido')
@section('page-subtitle', 'Habitaciones internas')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')
    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.card title="Agregar habitacion">
                <div class="card-body">
                    <form method="POST" action="{{ route('spaces.shared.rooms.store', $space) }}" data-ajax-form novalidate>
                        @csrf
                        @include('spaces.shared.partials.room-fields', ['room' => null])
                        <button class="btn btn-primary mt-3" type="submit">Agregar habitacion</button>
                    </form>
                </div>
            </x-ui.card>
        </div>
        <div class="col-lg-7">
            <x-ui.card title="Habitaciones registradas">
                <div class="card-body p-0">
                    @if ($space->rooms->isEmpty())
                        <div class="text-body-secondary text-center py-4">Agrega al menos una habitacion para poder publicar.</div>
                    @else
                        <div class="shared-room-sort-list" data-room-sort-list data-sort-url="{{ route('spaces.shared.rooms.order', $space) }}">
                            @foreach ($space->rooms as $room)
                                <div class="shared-room-sort-row" draggable="true" data-room-id="{{ $room->id }}">
                                    <button class="btn btn-icon btn-sm btn-ghost-secondary shared-room-drag-handle" type="button" title="Arrastrar para ordenar" aria-label="Arrastrar para ordenar">
                                        <i class="ti ti-grip-vertical"></i>
                                    </button>
                                    <div class="shared-room-sort-main">
                                        <div class="fw-semibold text-truncate">{{ $room->name ?: $room->title }}</div>
                                        <div class="text-body-secondary small text-truncate">
                                            {{ $room->bathroomType?->name ?: 'Baño pendiente' }} · {{ $room->beds->sum('quantity') }} cama{{ $room->beds->sum('quantity') === 1 ? '' : 's' }} · Cap. {{ $room->max_capacity ?: 0 }} · {{ ['full_room' => 'Completa', 'bed_unit' => 'Por cama', 'flexible' => 'Flexible'][$room->sale_mode ?? 'full_room'] }}
                                        </div>
                                    </div>
                                    <span class="badge text-bg-{{ $room->status === 'active' ? 'success' : 'secondary' }}">{{ $room->status }}</span>
                                    <button class="btn btn-outline-primary btn-icon btn-sm" type="button" title="Editar habitacion" aria-label="Editar habitacion" data-shared-room-edit-toggle="{{ $room->id }}">
                                        <i class="ti ti-pencil"></i>
                                    </button>
                                    <form method="POST" action="{{ route('spaces.shared.rooms.destroy', [$space, $room]) }}" data-ajax-form data-confirm-delete="Eliminar habitacion?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-icon btn-sm" type="submit" title="Eliminar habitacion" aria-label="Eliminar habitacion">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="shared-room-edit-panel d-none" data-shared-room-edit-panel="{{ $room->id }}">
                                    <form method="POST" action="{{ route('spaces.shared.rooms.update', [$space, $room]) }}" data-ajax-form novalidate>
                                        @csrf
                                        @method('PUT')
                                        @include('spaces.shared.partials.room-fields', ['room' => $room])
                                        <div class="d-flex justify-content-end gap-2 mt-3">
                                            <button class="btn btn-outline-secondary btn-sm" type="button" data-shared-room-edit-toggle="{{ $room->id }}">
                                                Cancelar
                                            </button>
                                            <button class="btn btn-primary btn-sm" type="submit">
                                                Guardar cambios
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </x-ui.card>
        </div>
    </div>
    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.shared.details.edit', $space) }}">Volver</a>
        <a class="btn btn-primary" href="{{ route('spaces.shared.beds.edit', $space) }}">Continuar</a>
    </div>
    </div>
@endsection
