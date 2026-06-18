@extends('layouts.admin')

@section('title', 'Disponibilidad | '.config('app.name', 'Base Admin'))
@section('page-title', 'Disponibilidad')
@section('page-subtitle', 'Estados diarios por espacio privado o habitación compartida')

@section('content')
    @php
        $spaceLabel = fn ($space) => $space->spaceMode?->slug === 'compartido'
            ? ($space->name ?: $space->title)
            : trim(($space->title ?: $space->name).(((int) $space->max_capacity > 0) ? ' - Cap. '.$space->max_capacity : ''));
        $roomLabel = fn ($room) => collect([
            $room->name ?: $room->title ?: 'Habitacion',
            filled($room->room_number) && trim((string) $room->room_number) !== trim((string) ($room->name ?: $room->title ?: 'Habitacion')) ? 'Hab. '.$room->room_number : null,
            $room->beds->map(fn ($bed) => trim($bed->quantity.' '.($bed->bedType?->name ?: 'cama')))->filter()->implode(', '),
        ])->filter()->implode(' - ');
        $bedUnitLabel = fn ($bedUnit) => collect([
            $bedUnit->label ?: 'Cama '.$bedUnit->id,
            $bedUnit->bedType?->name,
        ])->filter()->implode(' - ');
        $spacesPayload = $spaces->map(fn ($space) => [
            'id' => $space->id,
            'name' => $spaceLabel($space),
            'mode' => $space->spaceMode?->slug,
            'rooms' => $space->rooms->map(fn ($room) => [
                'id' => $room->id,
                'name' => $roomLabel($room),
                'bed_units' => $room->bedUnits->map(fn ($bedUnit) => [
                    'id' => $bedUnit->id,
                    'name' => $bedUnitLabel($bedUnit),
                ])->values(),
            ])->values(),
        ])->values();
    @endphp

    <div
        data-availability
        data-week-data-url="{{ route('availability.week-data') }}"
        data-bulk-update-url="{{ route('availability.bulk.update') }}"
        data-store-status-url="{{ route('availability.status.store') }}"
        data-update-status-url-template="{{ route('availability.status.update', ['availabilityStatus' => '__ID__']) }}"
        data-spaces='@json($spacesPayload)'
        data-initial-grid='@json($initialGrid)'
        data-can-manage="{{ auth()->user()?->can('availability.manage') ? '1' : '0' }}"
    >
        <div class="availability-toolbar">
            <div class="btn-list">
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-prev>
                    <i class="ti ti-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-today>Esta semana</button>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-availability-next>
                    <i class="ti ti-chevron-right"></i>
                </button>
                <input class="form-control form-control-sm availability-date" type="date" value="{{ $initialGrid['week_start'] }}" min="{{ now()->toDateString() }}" data-availability-picker>
            </div>
            @can('availability.manage')
                <button class="btn btn-primary btn-sm" type="button" data-availability-bulk-open>
                    <i class="ti ti-edit me-1"></i>Editar en bloque
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
                <label class="form-label" for="availability-filter-space">Espacio</label>
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
                    <option value="reserved">Reservado</option>
                    <option value="occupied">Ocupado</option>
                </select>
            </div>
            <div class="align-self-end">
                <button class="btn btn-outline-secondary btn-sm" type="reset">
                    <i class="ti ti-filter-off me-1"></i>Limpiar
                </button>
            </div>
        </form>

        <div class="availability-legend">
            <span><i class="availability-dot availability-dot-available"></i>Disponible</span>
            <span><i class="availability-dot availability-dot-closed"></i>Cerrado</span>
            <span><i class="availability-dot availability-dot-reserved"></i>Reservado</span>
            <span><i class="availability-dot availability-dot-occupied"></i>Ocupado</span>
        </div>

        <x-ui.card>
            <div class="card-body p-0">
                <div class="availability-grid-shell" data-availability-grid></div>
            </div>
        </x-ui.card>

        <div class="row g-3 mt-1" data-availability-summary></div>

        <div class="modal fade" tabindex="-1" aria-hidden="true" data-availability-status-modal>
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content" data-availability-status-form autocomplete="off">
                    <div class="modal-header">
                        <h5 class="modal-title">Cambiar disponibilidad</h5>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="space_id" data-availability-status-field="space_id">
                        <input type="hidden" name="space_room_id" data-availability-status-field="space_room_id">
                        <input type="hidden" name="room_bed_unit_id" data-availability-status-field="room_bed_unit_id">
                        <input type="hidden" name="date" data-availability-status-field="date">
                        <input type="hidden" name="availability_status_id" data-availability-status-field="availability_status_id">

                        <dl class="availability-status-meta">
                            <div><dt>Espacio</dt><dd data-availability-status-label="space"></dd></div>
                            <div><dt>Habitación</dt><dd data-availability-status-label="room"></dd></div>
                            <div><dt>Cama</dt><dd data-availability-status-label="bed_unit"></dd></div>
                            <div><dt>Fecha</dt><dd data-availability-status-label="date"></dd></div>
                        </dl>

                        <div class="mb-3">
                            <label class="form-label" for="availability-status">Estado</label>
                            <select class="form-select" id="availability-status" name="status" data-availability-status-field="status" required>
                                <option value="available">Disponible</option>
                                <option value="closed">Cerrado</option>
                                <option value="reserved">Reservado</option>
                                <option value="occupied">Ocupado</option>
                            </select>
                            <div class="invalid-feedback" data-error-for="status"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="availability-price">Precio por noche</label>
                            <div class="input-group">
                                <span class="input-group-text">Bs</span>
                                <input class="form-control" id="availability-price" name="price" type="number" min="0" step="0.01" inputmode="decimal" data-availability-status-field="price" placeholder="0.00">
                            </div>
                            <div class="form-hint">Este precio alimenta la disponibilidad pública y las cotizaciones.</div>
                            <div class="invalid-feedback d-block" data-error-for="price"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="availability-public-online">Reserva pública</label>
                            <select class="form-select" id="availability-public-online" name="is_public_online" data-availability-status-field="is_public_online">
                                <option value="1">En línea para reserva pública</option>
                                <option value="0">Fuera de línea para reserva pública</option>
                            </select>
                            <div class="form-hint">No afecta uso interno, ocupabilidad ni check-in.</div>
                            <div class="invalid-feedback" data-error-for="is_public_online"></div>
                        </div>

                        <div>
                            <label class="form-label" for="availability-notes">Nota opcional</label>
                            <textarea class="form-control" id="availability-notes" name="notes" rows="3" data-availability-status-field="notes" placeholder="Mantenimiento, uso interno, separación manual..."></textarea>
                            <div class="invalid-feedback" data-error-for="notes"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                        @can('availability.manage')
                            <button class="btn btn-primary" type="submit">Guardar estado</button>
                        @endcan
                    </div>
                </form>
            </div>
        </div>

        @can('availability.manage')
            <div class="modal fade" tabindex="-1" aria-hidden="true" data-availability-bulk-modal>
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <form class="modal-content" data-availability-bulk-form autocomplete="off">
                        <div class="modal-header">
                            <h5 class="modal-title">Modificar disponibilidad en bloque</h5>
                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="availability-bulk-grid">
                                <div>
                                    <label class="form-label" for="availability-bulk-space">Espacio</label>
                                    <select class="form-select" id="availability-bulk-space" name="space_id" data-availability-bulk-field="space_id" required>
                                        <option value="">Selecciona un espacio</option>
                                        @foreach ($spaces as $space)
                                            <option value="{{ $space->id }}">{{ $spaceLabel($space) }}</option>
                                        @endforeach
                                    </select>
                                    <div class="invalid-feedback" data-error-for="space_id"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-room">Habitación</label>
                                    <select class="form-select" id="availability-bulk-room" name="space_room_id" data-availability-bulk-field="space_room_id" disabled>
                                        <option value="">No aplica</option>
                                    </select>
                                    <div class="invalid-feedback" data-error-for="space_room_id"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-bed-unit">Cama</label>
                                    <select class="form-select" id="availability-bulk-bed-unit" name="room_bed_unit_id" data-availability-bulk-field="room_bed_unit_id" disabled>
                                        <option value="">Toda la habitación</option>
                                    </select>
                                    <div class="invalid-feedback" data-error-for="room_bed_unit_id"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-start-date">Desde</label>
                                    <input class="form-control" id="availability-bulk-start-date" name="start_date" type="date" min="{{ now()->toDateString() }}" data-availability-bulk-field="start_date" required>
                                    <div class="invalid-feedback" data-error-for="start_date"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-end-date">Hasta</label>
                                    <input class="form-control" id="availability-bulk-end-date" name="end_date" type="date" min="{{ now()->toDateString() }}" data-availability-bulk-field="end_date" required>
                                    <div class="invalid-feedback" data-error-for="end_date"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-status">Estado</label>
                                    <select class="form-select" id="availability-bulk-status" name="status" data-availability-bulk-field="status">
                                        <option value="">No cambiar estado</option>
                                        <option value="available">Disponible</option>
                                        <option value="closed">Cerrado</option>
                                        <option value="reserved">Reservado</option>
                                        <option value="occupied">Ocupado</option>
                                    </select>
                                    <div class="invalid-feedback" data-error-for="status"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-price">Precio por noche</label>
                                    <div class="input-group">
                                        <span class="input-group-text">Bs</span>
                                        <input class="form-control" id="availability-bulk-price" name="price" type="number" min="0" step="0.01" inputmode="decimal" data-availability-bulk-field="price" placeholder="No cambiar precio">
                                    </div>
                                    <div class="invalid-feedback d-block" data-error-for="price"></div>
                                </div>

                                <div>
                                    <label class="form-label" for="availability-bulk-public-online">Reserva pública</label>
                                    <select class="form-select" id="availability-bulk-public-online" name="is_public_online" data-availability-bulk-field="is_public_online">
                                        <option value="">No cambiar publicación</option>
                                        <option value="1">En línea para reserva pública</option>
                                        <option value="0">Fuera de línea para reserva pública</option>
                                    </select>
                                    <div class="invalid-feedback" data-error-for="is_public_online"></div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <label class="form-label" for="availability-bulk-notes">Nota opcional</label>
                                <textarea class="form-control" id="availability-bulk-notes" name="notes" rows="3" data-availability-bulk-field="notes" placeholder="Mantenimiento, tarifa especial, separación manual..."></textarea>
                                <div class="invalid-feedback" data-error-for="notes"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-primary" type="submit">Aplicar cambios</button>
                        </div>
                    </form>
                </div>
            </div>
        @endcan
    </div>
@endsection
