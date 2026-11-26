@php
    $money = fn ($value) => number_format((float) $value, 2).' Bs';
    $resourceLabel = function ($reservation): string {
        if ($reservation->bedUnitItems->isNotEmpty()) {
            return $reservation->bedUnitItems
                ->map(fn ($item) => collect([
                    $reservation->space?->title ?: $reservation->space?->name,
                    $item->bedUnit?->room?->name ?: $item->bedUnit?->room?->title,
                    $item->bedUnit?->label,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        if ($reservation->roomItems->isNotEmpty()) {
            return $reservation->roomItems
                ->map(fn ($item) => collect([
                    $reservation->space?->title ?: $reservation->space?->name,
                    $item->room?->name ?: $item->room?->title,
                ])->filter()->implode(' / '))
                ->implode(', ');
        }

        return collect([
            $reservation->space?->title ?: $reservation->space?->name,
            $reservation->room?->name ?: $reservation->room?->title,
        ])->filter()->implode(' / ') ?: 'Recurso no asignado';
    };
    $isMovable = $reservation->shouldBlockAvailability();
    $canMove = $isMovable && count($resources) > 0;
    $currentBedId = $reservation->bedUnitItems->first()?->room_bed_unit_id;
    $currentRoomId = $reservation->roomItems->first()?->space_room_id ?? $reservation->space_room_id;
    $currentFullRoom = ! $currentBedId && $currentRoomId;
    $selectedKey = $currentBedId ? 'bed:'.$currentBedId : ($currentRoomId ? 'room:'.$currentRoomId : 'space:'.$reservation->space_id);
    if ($currentFullRoom) {
        $flexibleBed = collect($resources)->first(fn ($resource) => $resource['resource_type'] === 'shared_bed_unit' && $resource['space_room_id'] === $currentRoomId && $resource['sale_mode'] === 'flexible' && ! $resource['disabled']);
        if ($flexibleBed) $selectedKey = $flexibleBed['key'];
    }

@endphp

<form
    method="POST"
    action="{{ route('admin.reservations.move.store', $reservation) }}"
    autocomplete="off"
    novalidate
    data-ajax-form
    data-room-change-form
    data-reservation-move-form
    data-reservation-move-url="{{ route('admin.reservations.move.create', $reservation) }}"
>
    @csrf

    <div class="vstack gap-3">
        <div class="alert alert-info mb-0">
            <div class="fw-semibold">Mover reserva {{ $reservation->code }}</div>
            <div>Selecciona una habitación completa o una cama disponible. También puedes cambiar las fechas; el ingreso no puede ser anterior a hoy.</div>
        </div>

        <div class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Recurso actual</label>
                <div class="form-control-plaintext fw-semibold">{{ $resourceLabel($reservation) }}</div>
            </div>
            <div class="col-md-5">
                <label class="form-label">Saldo actual</label>
                <div class="form-control-plaintext fw-semibold"><x-ui.money :amount="$reservation->reservationGroup?->accountStatement?->balance ?? $reservation->balance_amount" :exchange-rate="$currentExchangeRate?->rate" /></div>
            </div>
        </div>

        @if (! $isMovable)
            <div class="alert alert-warning mb-0">Solo se pueden mover reservas pendientes, en revision o confirmadas.</div>
        @elseif (count($resources) === 0)
            <div class="alert alert-warning mb-0">No hay recursos disponibles para el rango seleccionado.</div>
        @endif

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="reservation-move-check-in">Ingreso</label>
                <input class="form-control @error('check_in') is-invalid @enderror" id="reservation-move-check-in" name="check_in" type="date" min="{{ today()->toDateString() }}" value="{{ old('check_in', $checkIn->toDateString()) }}" data-reservation-move-date required @disabled(! $isMovable)>
                <div class="invalid-feedback" data-error-for="check_in">{{ $errors->first('check_in') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="reservation-move-check-out">Salida</label>
                <input class="form-control @error('check_out') is-invalid @enderror" id="reservation-move-check-out" name="check_out" type="date" min="{{ $checkIn->addDay()->toDateString() }}" value="{{ old('check_out', $checkOut->toDateString()) }}" data-reservation-move-date required @disabled(! $isMovable)>
                <div class="invalid-feedback" data-error-for="check_out">{{ $errors->first('check_out') }}</div>
            </div>
        </div>

        <div>
            <label class="form-label" for="reservation-move-resource">Nuevo recurso</label>
            <select class="form-select @error('space_id') is-invalid @enderror" id="reservation-move-resource" data-room-change-resource required @disabled(! $canMove)>
                <option value="">Seleccionar</option>
                @foreach ($resources as $resource)
                    @if ($resource['resource_type'] === 'shared_room' && $resource['sale_mode'] === 'flexible' && collect($resources)->contains(fn ($item) => $item['resource_type'] === 'shared_bed_unit' && $item['space_room_id'] === $resource['space_room_id']))
                        @continue
                    @endif
                    <option
                        value="{{ $resource['key'] }}"
                        data-resource-type="{{ $resource['resource_type'] }}"
                        data-space-id="{{ $resource['space_id'] }}"
                        data-room-id="{{ $resource['space_room_id'] }}"
                        data-bed-unit-id="{{ $resource['room_bed_unit_id'] ?? '' }}"
                        data-capacity="{{ $resource['capacity'] }}"
                        data-sale-mode="{{ $resource['sale_mode'] }}"
                        data-full-room-available="{{ $resource['full_room_available'] ? '1' : '0' }}"
                        data-full-room-capacity="{{ $resource['full_room_capacity'] }}"
                        @selected($resource['key'] === $selectedKey && ! $resource['disabled'])
                        @disabled($resource['disabled'])
                    >
                        {{ $resource['label'] }} · {{ match ($resource['resource_type']) { 'shared_room' => 'Habitación completa', 'shared_bed_unit' => 'Cama', default => 'Espacio completo' } }} · Cap. {{ $resource['capacity'] }}{{ $resource['disabled'] ? ' · No disponible' : '' }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="space_id">{{ $errors->first('space_id') ?: $errors->first('space_room_id') ?: $errors->first('room_bed_unit_id') }}</div>
            <div class="form-hint" data-room-change-hint></div>
            <div class="d-none mt-2" data-move-full-room-wrap>
                <label class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" data-move-full-room-switch @checked($currentFullRoom)>
                    <span class="form-check-label">Vender habitación completa</span>
                </label>
                <div class="form-hint" data-move-full-room-hint></div>
            </div>
        </div>

        <input type="hidden" name="resource_type" data-room-change-resource-type>
        <input type="hidden" name="space_id" data-room-change-space-id>
        <input type="hidden" name="space_room_id" data-room-change-room-id>
        <input type="hidden" name="room_bed_unit_id" data-room-change-bed-unit-id>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="reservation-move-price">Precio noche BOB</label>
                <input class="form-control @error('price_per_night') is-invalid @enderror" id="reservation-move-price" name="price_per_night" type="number" min="0" step="0.01" value="{{ old('price_per_night', number_format((float) $reservation->price_per_person, 2, '.', '')) }}" required @disabled(! $canMove)>
                <div class="invalid-feedback" data-error-for="price_per_night">{{ $errors->first('price_per_night') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="reservation-move-notes">Notas</label>
                <input class="form-control @error('notes') is-invalid @enderror" id="reservation-move-notes" name="notes" value="{{ old('notes') }}" placeholder="Motivo o detalle opcional" @disabled(! $canMove)>
                <div class="invalid-feedback" data-error-for="notes">{{ $errors->first('notes') }}</div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" type="submit" @disabled(! $canMove)>
                <span class="spinner-border spinner-border-sm d-none me-1" data-submit-spinner></span>
                <i class="ti ti-switch-horizontal me-1"></i>Mover reserva
            </button>
        </div>
    </div>
</form>
