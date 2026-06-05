@extends('layouts.admin')

@section('title', 'Disponibilidad | '.config('app.name', 'Base Admin'))
@section('page-title', 'Disponibilidad')
@section('page-subtitle', 'Control diario de precios y disponibilidad por espacio o habitacion')

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
        data-availability
        data-grid-data-url="{{ route('availability.grid-data') }}"
        data-store-day-url="{{ route('availability.day.store') }}"
        data-bulk-url="{{ route('availability.bulk') }}"
        data-spaces='@json($spacesPayload)'
        data-initial-grid='@json($initialGrid)'
        data-can-manage="{{ auth()->user()?->can('availability.manage') ? '1' : '0' }}"
    >
        <div class="availability-toolbar">
            <div class="btn-list">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-prev>
                    <i class="ti ti-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-today>Hoy</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-next>
                    <i class="ti ti-chevron-right"></i>
                </button>
                <input class="form-control form-control-sm availability-date" type="date" value="{{ $initialGrid['start_date'] }}" data-availability-picker>
            </div>

            @can('availability.manage')
                <button class="btn btn-primary btn-sm" type="button" data-availability-bulk-open>
                    <i class="ti ti-stack-push me-1"></i>Accion en bloque
                </button>
            @endcan
        </div>

        <form class="availability-filters" autocomplete="off" data-availability-filters>
            <div>
                <label class="form-label" for="availability-filter-type">Tipo</label>
                <select class="form-select form-select-sm" id="availability-filter-type" name="type">
                    <option value="all">Todos</option>
                    <option value="private">Privados</option>
                    <option value="shared">Compartidos</option>
                </select>
            </div>
            <div>
                <label class="form-label" for="availability-filter-space">Alojamiento</label>
                <select class="form-select form-select-sm" id="availability-filter-space" name="space_id">
                    <option value="">Todos</option>
                    @foreach ($spaces as $space)
                        <option value="{{ $space->id }}">{{ $spaceLabel($space) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label" for="availability-filter-status">Estado</label>
                <select class="form-select form-select-sm" id="availability-filter-status" name="status">
                    <option value="">Todos</option>
                    <option value="available">Disponible</option>
                    <option value="closed">Cerrado</option>
                    <option value="sold_out">Agotado</option>
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
                <div class="availability-grid-shell" data-availability-grid></div>
            </div>
        </x-ui.card>

        <div class="modal fade" tabindex="-1" aria-hidden="true" data-availability-bulk-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content" data-availability-bulk-form autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Accion en bloque</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="availability-bulk-type">Tipo</label>
                                <select class="form-select" id="availability-bulk-type" name="type" data-availability-bulk-type>
                                    <option value="all">Todos</option>
                                    <option value="private">Privados</option>
                                    <option value="shared">Compartidos</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="availability-bulk-space">Alojamiento</label>
                                <select class="form-select" id="availability-bulk-space" name="space_id" data-availability-bulk-space>
                                    <option value="">Todos</option>
                                    @foreach ($spaces as $space)
                                        <option value="{{ $space->id }}">{{ $spaceLabel($space) }}</option>
                                    @endforeach
                                </select>
                                <div class="invalid-feedback" data-error-for="space_id"></div>
                            </div>
                            <div class="col-md-6 d-none" data-availability-bulk-room-wrap>
                                <label class="form-label" for="availability-bulk-room">Habitacion</label>
                                <select class="form-select" id="availability-bulk-room" name="space_room_id" data-availability-bulk-room>
                                    <option value="">Todas</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="space_room_id"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="availability-bulk-start">Fecha inicio</label>
                                <input class="form-control" id="availability-bulk-start" name="start_date" type="date" required data-availability-bulk-start>
                                <div class="invalid-feedback" data-error-for="start_date"></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="availability-bulk-end">Fecha fin</label>
                                <input class="form-control" id="availability-bulk-end" name="end_date" type="date" required data-availability-bulk-end>
                                <div class="invalid-feedback" data-error-for="end_date"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check mb-2">
                                    <input class="form-check-input" name="apply_price" type="checkbox" value="1" data-availability-bulk-apply-price>
                                    <span class="form-check-label">Aplicar precio</span>
                                </label>
                                <input class="form-control" name="price" type="number" min="0" step="0.01" placeholder="Precio" data-availability-bulk-price>
                                <div class="invalid-feedback" data-error-for="apply_price"></div>
                                <div class="invalid-feedback" data-error-for="price"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-check mb-2">
                                    <input class="form-check-input" name="apply_status" type="checkbox" value="1" data-availability-bulk-apply-status>
                                    <span class="form-check-label">Aplicar estado</span>
                                </label>
                                <select class="form-select" name="status" data-availability-bulk-status>
                                    <option value="available">Disponible</option>
                                    <option value="closed">Cerrado</option>
                                </select>
                                <div class="invalid-feedback" data-error-for="status"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        @can('availability.manage')
                            <button class="btn btn-primary" type="submit">Aplicar</button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
