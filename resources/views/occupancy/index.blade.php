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
        $privateSpaces = $spaces->filter(fn ($space) => $space->spaceMode?->slug === 'privado')->values();
        $sharedSpaces = $spaces->filter(fn ($space) => $space->spaceMode?->slug === 'compartido')->values();
        $activeView = $filters['view'] ?? ($privateSpaces->isNotEmpty() ? 'private' : ($sharedSpaces->first() ? 'shared:'.$sharedSpaces->first()->id : 'private'));
    @endphp

    <div
        data-occupancy-week
        data-week-data-url="{{ route('occupancy.week-data') }}"
        data-cell-actions-url="{{ route('occupancy.cell-actions') }}"
        data-check-in-summary-modal-url="{{ route('occupancy.check-in.summary-modal') }}"
        data-check-in-modal-url="{{ route('occupancy.check-in.modal') }}"
        data-check-in-create-url="{{ route('check-ins.create') }}"
        data-reservation-create-url="{{ route('internal-reservations.create') }}"
        data-reservation-payment-create-url-template="{{ route('admin.reservation-groups.payments.create', ['group' => '__ID__']) }}"
        data-stay-payment-create-url-template="{{ route('stays.payments.create', ['stay' => '__ID__']) }}"
        data-check-out-modal-url="{{ route('occupancy.check-out.modal') }}"
        data-check-out-store-url-template="{{ route('occupancy.check-out.store', ['stay' => '__ID__']) }}"
        data-extra-charge-modal-url="{{ route('occupancy.extra-charge.modal') }}"
        data-block-modal-url="{{ route('occupancy.block.modal') }}"
        data-store-url="{{ route('occupancy.blocks.store') }}"
        data-update-url-template="{{ route('occupancy.blocks.update', ['occupancyBlock' => '__ID__']) }}"
        data-destroy-url-template="{{ route('occupancy.blocks.destroy', ['occupancyBlock' => '__ID__']) }}"
        data-spaces='@json($spacesPayload)'
        data-initial-week='@json($initialWeek)'
        data-can-manage="{{ auth()->user()?->can('occupancy.manage') ? '1' : '0' }}"
    >
        <form class="occupancy-week-toolbar" autocomplete="off" data-occupancy-filters>
            <input type="hidden" name="view" value="{{ $activeView }}" data-occupancy-view-input>

            <div class="occupancy-view-tabs-wrap">
                <ul class="nav nav-tabs occupancy-view-tabs" role="tablist">
                    @if ($privateSpaces->isNotEmpty())
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ $activeView === 'private' ? 'active' : '' }}"
                                type="button"
                                role="tab"
                                data-occupancy-view-tab
                                data-view="private"
                            >
                                <i class="ti ti-home me-1"></i>Privados
                            </button>
                        </li>
                    @endif
                    @foreach ($sharedSpaces as $space)
                        @php($viewKey = 'shared:'.$space->id)
                        <li class="nav-item" role="presentation">
                            <button
                                class="nav-link {{ $activeView === $viewKey ? 'active' : '' }}"
                                type="button"
                                role="tab"
                                data-occupancy-view-tab
                                data-view="{{ $viewKey }}"
                            >
                                <i class="ti ti-building me-1"></i>{{ $space->name ?: $space->title }}
                            </button>
                        </li>
                    @endforeach
                </ul>
            </div>
             <div class="btn-list occupancy-week-controls">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-prev>
                    <i class="ti ti-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-today>Hoy</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-occupancy-week-next>
                    <i class="ti ti-chevron-right"></i>
                </button>
                <input class="form-control form-control-sm occupancy-week-date" type="date" value="{{ $initialWeek['week_start'] }}" data-occupancy-week-picker>
            </div>
        </form>

        <x-ui.card>
            <div class="card-body p-0">
                <div class="occupancy-grid-shell" data-occupancy-grid></div>
            </div>
        </x-ui.card>

        <div class="occupancy-cell-actions-popover d-none" data-occupancy-actions-popover></div>

        <div class="modal fade" tabindex="-1" aria-hidden="true" data-occupancy-action-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" data-occupancy-action-modal-title>Gestion de ocupabilidad</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body" data-occupancy-action-modal-body></div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" tabindex="-1" aria-hidden="true" data-occupancy-modal>
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form class="modal-content" data-occupancy-form autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title" data-occupancy-modal-title>Nuevo bloqueo</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="block_id" data-occupancy-block-id>
                        <input type="hidden" name="room_bed_unit_id" data-occupancy-bed-unit-id>
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
