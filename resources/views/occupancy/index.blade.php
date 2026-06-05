@extends('layouts.admin')

@section('title', 'Ocupabilidad semanal | '.config('app.name', 'Base Admin'))
@section('page-title', 'Ocupabilidad semanal')
@section('page-subtitle', 'Grilla operativa por dias/noches para espacios y habitaciones')

@section('content')
    @php
        $spaceLabel = fn ($space) => $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title)
            : trim(($space->title ?: $space->name).(((int) $space->max_capacity > 0) ? ' - Cap. '.$space->max_capacity : ''));
        $roomLabel = fn ($room) => trim(($room->name ?: $room->title).' - '.$room->beds->map(fn ($bed) => trim($bed->quantity.' '.($bed->bedType?->name ?: 'cama')))->filter()->implode(', '), ' -');
        $spacesPayload = $spaces->map(fn ($space) => [
            'id' => $space->id,
            'name' => $spaceLabel($space),
            'mode' => $space->spaceMode?->slug,
            'rooms' => $space->rooms->map(fn ($room) => [
                'id' => $room->id,
                'name' => $roomLabel($room),
            ])->values(),
        ])->values();
    @endphp

    <div
        data-occupancy-week
        data-week-data-url="{{ route('occupancy.week-data') }}"
        data-store-url="{{ route('occupancy.blocks.store') }}"
        data-update-url-template="{{ route('occupancy.blocks.update', ['occupancyBlock' => '__ID__']) }}"
        data-destroy-url-template="{{ route('occupancy.blocks.destroy', ['occupancyBlock' => '__ID__']) }}"
        data-spaces='@json($spacesPayload)'
        data-initial-week='@json($initialWeek)'
        data-can-manage="{{ auth()->user()?->can('occupancy.manage') ? '1' : '0' }}"
    >
        <div class="occupancy-week-toolbar">
            <div class="btn-list">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-prev>
                    <i class="ti ti-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-today>Hoy</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-next>
                    <i class="ti ti-chevron-right"></i>
                </button>
                <input class="form-control form-control-sm occupancy-week-date" type="date" value="{{ $initialWeek['week_start'] }}" data-occupancy-week-picker>
            </div>

            @can('occupancy.manage')
                <button class="btn btn-primary btn-sm" type="button" data-occupancy-new-block>
                    <i class="ti ti-calendar-plus me-1"></i>Nuevo bloqueo
                </button>
            @endcan
        </div>

        <form class="occupancy-week-filters" autocomplete="off" data-occupancy-filters>
            <div>
                <label class="form-label" for="occupancy-filter-type">Tipo</label>
                <select class="form-select form-select-sm" id="occupancy-filter-type" name="type">
                    <option value="all">Todos</option>
                    <option value="private">Privados</option>
                    <option value="shared">Compartidos</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="occupancy-filter-space">Alojamiento</label>
                <select class="form-select form-select-sm" id="occupancy-filter-space" name="space_id">
                    <option value="">Todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}">
                            {{ $spaceLabel($space) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="occupancy-filter-status">Estado</label>
                <select class="form-select form-select-sm" id="occupancy-filter-status" name="status">
                    <option value="">Todos</option>
                    <option value="available">Libre</option>
                    <option value="manual_block">Bloqueado</option>
                    <option value="maintenance">Mantenimiento</option>
                    <option value="owner_use">Uso propietario</option>
                    <option value="unavailable">No disponible</option>
                </select>
            </div>
            <div class="align-self-end">
                <button class="btn btn-outline-secondary btn-sm" type="reset">
                    <i class="ti ti-filter-off me-1"></i>Limpiar
                </button>
            </div>
        </form>

        <x-ui.card>
            <div class="card-body p-0">
                <div class="occupancy-grid-shell" data-occupancy-grid></div>
            </div>
        </x-ui.card>

        <div class="modal fade" tabindex="-1" aria-hidden="true" data-occupancy-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content" data-occupancy-form autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" data-occupancy-modal-title>Nuevo bloqueo</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="block_id" data-occupancy-block-id>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="occupancy-space">Espacio</label>
                                <select class="form-select" id="occupancy-space" name="space_id" required data-occupancy-space-select>
                                    <option value="">Seleccionar</option>
                                    @foreach ($spaces as $space)
                                        <option value="{{ $space->id }}">
                                            {{ $space->spaceMode?->slug === 'compartido' ? ($space->name ?: $space->title) : ($space->title ?: $space->name) }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="space_id"></div>
                            </div>
                            <div class="col-md-6 d-none" data-occupancy-room-wrap>
                                <label class="form-label" for="occupancy-room">Habitacion</label>
                                <select class="form-select" id="occupancy-room" name="space_room_id" data-occupancy-room-select>
                                    <option value="">Seleccionar</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="space_room_id"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="occupancy-start">Fecha inicio</label>
                                <input class="form-control" id="occupancy-start" name="start_date" type="date" required>
                                <div class="invalid-feedback" data-error-for="start_date"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="occupancy-end">Fecha fin</label>
                                <input class="form-control" id="occupancy-end" name="end_date" type="date" required>
                                <div class="invalid-feedback" data-error-for="end_date"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="occupancy-type">Tipo</label>
                                <select class="form-select" id="occupancy-type" name="type" required>
                                    <option value="manual_block">Bloqueo manual</option>
                                    <option value="maintenance">Mantenimiento</option>
                                    <option value="owner_use">Uso propietario</option>
                                    <option value="unavailable">No disponible</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="type"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="occupancy-title">Titulo</label>
                                <input class="form-control" id="occupancy-title" name="title" required maxlength="255">
                                <div class="invalid-feedback" data-error-for="title"></div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="occupancy-description">Descripcion</label>
                                <textarea class="form-control" id="occupancy-description" name="description" rows="3" maxlength="3000"></textarea>
                                <div class="invalid-feedback" data-error-for="description"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        @can('occupancy.manage')
                            <button class="btn btn-outline-danger d-none" type="button" data-occupancy-delete>
                                <i class="ti ti-trash me-1"></i>Eliminar
                            </button>
                        @endcan
                        <div class="btn-list ms-auto">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                            @can('occupancy.manage')
                                <button class="btn btn-primary" type="submit">Guardar bloqueo</button>
                            @endcan
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
