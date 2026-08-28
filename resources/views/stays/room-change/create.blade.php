@php
    $money = fn ($value) => number_format((float) $value, 2).' Bs';
    $resourceLabel = collect([
        $stay->space?->title ?: $stay->space?->name,
        $stay->room?->name ?: $stay->room?->title,
        $stay->bedUnit?->label,
    ])->filter()->implode(' / ') ?: 'Recurso no asignado';
    $canMove = $stay->status === 'occupied' && $moveStart->lt($checkOut) && count($resources) > 0;
@endphp

<form method="POST" action="{{ route('stays.room-change.store', $stay) }}" autocomplete="off" novalidate data-ajax-form data-room-change-form>
    @csrf

    <div class="vstack gap-3">
        <div class="alert alert-info mb-0">
            <div class="fw-semibold">Cambio de habitacion</div>
            <div>Se moveran {{ $nights }} noche(s), desde {{ $moveStart->format('d/m/Y') }} hasta {{ $checkOut->format('d/m/Y') }}.</div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Habitacion actual</label>
                <div class="form-control-plaintext fw-semibold">{{ $resourceLabel }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Saldo actual</label>
                <div class="form-control-plaintext fw-semibold"><x-ui.money :amount="$stay->accountStatement?->balance ?? 0" :exchange-rate="$stay->exchange_rate" /></div>
            </div>
        </div>

        @if ($stay->status !== 'occupied')
            <div class="alert alert-warning mb-0">Solo se puede cambiar habitacion de una estancia ocupada.</div>
        @elseif (! $moveStart->lt($checkOut))
            <div class="alert alert-warning mb-0">No quedan noches pendientes para mover.</div>
        @elseif (count($resources) === 0)
            <div class="alert alert-warning mb-0">No hay habitaciones disponibles para las noches restantes.</div>
        @endif

        <div>
            <label class="form-label" for="room-change-resource">Nueva habitacion</label>
            <select class="form-select @error('space_id') is-invalid @enderror" id="room-change-resource" data-room-change-resource required @disabled(! $canMove)>
                <option value="">Seleccionar</option>
                @foreach ($resources as $resource)
                    <option
                        value="{{ $resource['key'] }}"
                        data-resource-type="{{ $resource['resource_type'] }}"
                        data-space-id="{{ $resource['space_id'] }}"
                        data-room-id="{{ $resource['space_room_id'] }}"
                        data-bed-unit-id="{{ $resource['room_bed_unit_id'] ?? '' }}"
                        data-capacity="{{ $resource['capacity'] }}"
                    >
                        {{ $resource['label'] }} · Cap. {{ $resource['capacity'] }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $errors->first('space_id') ?: $errors->first('space_room_id') ?: $errors->first('room_bed_unit_id') }}</div>
            <div class="form-hint" data-room-change-hint></div>
        </div>

        <input type="hidden" name="resource_type" data-room-change-resource-type>
        <input type="hidden" name="space_id" data-room-change-space-id>
        <input type="hidden" name="space_room_id" data-room-change-room-id>
        <input type="hidden" name="room_bed_unit_id" data-room-change-bed-unit-id>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="room-change-price">Precio noche BOB</label>
                <input class="form-control @error('price_per_night_bob') is-invalid @enderror" id="room-change-price" name="price_per_night_bob" type="number" min="0" step="0.01" value="{{ old('price_per_night_bob', number_format((float) $stay->price_per_night_bob, 2, '.', '')) }}" required @disabled(! $canMove)>
                <div class="invalid-feedback">{{ $errors->first('price_per_night_bob') }}</div>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="room-change-notes">Notas</label>
                <input class="form-control @error('notes') is-invalid @enderror" id="room-change-notes" name="notes" value="{{ old('notes') }}" placeholder="Motivo o detalle opcional" @disabled(! $canMove)>
                <div class="invalid-feedback">{{ $errors->first('notes') }}</div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
            <button class="btn btn-primary" type="submit" @disabled(! $canMove)>
                <span class="spinner-border spinner-border-sm d-none me-1" data-submit-spinner></span>
                <i class="ti ti-switch-horizontal me-1"></i>Cambiar habitacion
            </button>
        </div>
    </div>
</form>
