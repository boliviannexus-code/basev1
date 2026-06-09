@php
    $selectedKey = $stay['resource_key'] ?? '';
    if (! $selectedKey && ! empty($stay['room_bed_unit_id'])) {
        $selectedKey = 'bed:'.$stay['room_bed_unit_id'];
    } elseif (! $selectedKey && ! empty($stay['space_room_id'])) {
        $selectedKey = 'room:'.$stay['space_room_id'];
    } elseif (! $selectedKey && ! empty($stay['space_id'])) {
        $selectedKey = 'space:'.$stay['space_id'];
    }
    $selectedResource = collect($resources)->firstWhere('key', $selectedKey);
    $field = fn (string $name) => "stays[{$index}][{$name}]";
    $guestRows = old("stays.{$index}.guests", $stay['guests'] ?? []);
@endphp

<div class="check-in-stay-row" data-check-in-stay-row>
    <input type="hidden" name="{{ $field('resource_type') }}" value="{{ old("stays.{$index}.resource_type", $stay['resource_type'] ?? $selectedResource['resource_type'] ?? 'private_space') }}" data-stay-resource-type>
    <input type="hidden" name="{{ $field('space_id') }}" value="{{ old("stays.{$index}.space_id", $stay['space_id'] ?? $selectedResource['space_id'] ?? '') }}" data-stay-space-id>
    <input type="hidden" name="{{ $field('space_room_id') }}" value="{{ old("stays.{$index}.space_room_id", $stay['space_room_id'] ?? $selectedResource['space_room_id'] ?? '') }}" data-stay-room-id>
    <input type="hidden" name="{{ $field('room_bed_unit_id') }}" value="{{ old("stays.{$index}.room_bed_unit_id", $stay['room_bed_unit_id'] ?? $selectedResource['room_bed_unit_id'] ?? '') }}" data-stay-bed-unit-id>
    <input type="hidden" name="{{ $field('exchange_rate') }}" value="{{ old("stays.{$index}.exchange_rate", $stay['exchange_rate'] ?? '') }}" data-stay-exchange-rate>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-2 mb-3">
        <div>
            <div class="fw-semibold">Estancia <span data-stay-number>{{ is_numeric($index) ? ((int) $index + 1) : '__NUMBER__' }}</span></div>
            <div class="text-body-secondary small" data-stay-holder-label>Titular: huesped principal</div>
        </div>
        <div class="check-in-stay-actions">
            <div class="d-none text-end" data-stay-full-room-wrap>
                <label class="check-in-switch check-in-switch-sm">
                    <input class="form-check-input" type="checkbox" value="1" data-stay-full-room-switch>
                    <span>Habitacion completa</span>
                </label>
                <div class="form-hint" data-stay-full-room-hint></div>
            </div>
            <button class="btn btn-outline-danger btn-sm" type="button" data-check-in-remove-stay>
                <i class="ti ti-trash me-1"></i>Quitar
            </button>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12">
            <label class="form-label">Espacio / habitacion</label>
            <select class="form-select @error("stays.{$index}.space_id") is-invalid @enderror" data-stay-resource-select required>
                <option value="">Seleccionar</option>
                @foreach ($resources as $resource)
                    <option
                        value="{{ $resource['key'] }}"
                        data-resource-type="{{ $resource['resource_type'] }}"
                        data-space-id="{{ $resource['space_id'] }}"
                        data-room-id="{{ $resource['space_room_id'] }}"
                        data-bed-unit-id="{{ $resource['room_bed_unit_id'] ?? '' }}"
                        data-capacity="{{ $resource['capacity'] }}"
                        data-status="{{ $resource['status'] }}"
                        data-sale-mode="{{ $resource['sale_mode'] ?? '' }}"
                        data-full-room-key="{{ $resource['full_room_key'] ?? '' }}"
                        data-full-room-available="{{ ! empty($resource['full_room_available']) ? '1' : '0' }}"
                        data-full-room-capacity="{{ $resource['full_room_capacity'] ?? '' }}"
                        data-full-room-status="{{ $resource['full_room_status'] ?? '' }}"
                        @disabled($resource['disabled'])
                        @selected($selectedKey === $resource['key'])
                    >
                        {{ $resource['label'] }} · Cap. {{ $resource['capacity'] }} · {{ $statusLabels[$resource['status']] ?? $resource['status'] }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $errors->first("stays.{$index}.space_id") ?: $errors->first("stays.{$index}.space_room_id") ?: $errors->first("stays.{$index}.room_bed_unit_id") }}</div>
            <div class="form-hint" data-stay-resource-hint></div>
        </div>
        <div class="col-sm-6 col-lg-2">
            <label class="form-label">Personas</label>
            <input class="form-control @error("stays.{$index}.people_count") is-invalid @enderror" name="{{ $field('people_count') }}" type="number" min="1" value="{{ old("stays.{$index}.people_count", $stay['people_count'] ?? 1) }}" autocomplete="off" required data-stay-people>
            <div class="invalid-feedback">{{ $errors->first("stays.{$index}.people_count") }}</div>
        </div>
        <input type="hidden" name="{{ $field('currency') }}" value="BOB" data-stay-currency>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label">Precio noche BOB</label>
            <input class="form-control @error("stays.{$index}.price_per_night_bob") is-invalid @enderror" name="{{ $field('price_per_night_bob') }}" type="number" min="0" step="0.01" value="{{ old("stays.{$index}.price_per_night_bob", $stay['price_per_night_bob'] ?? '') }}" autocomplete="off" data-stay-price-bob>
            <div class="invalid-feedback">{{ $errors->first("stays.{$index}.price_per_night_bob") }}</div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <label class="form-label">Precio noche USD</label>
            <input class="form-control @error("stays.{$index}.price_per_night_usd") is-invalid @enderror" name="{{ $field('price_per_night_usd') }}" type="number" min="0" step="0.01" value="{{ old("stays.{$index}.price_per_night_usd", $stay['price_per_night_usd'] ?? '') }}" autocomplete="off" data-stay-price-usd>
            <div class="invalid-feedback">{{ $errors->first("stays.{$index}.price_per_night_usd") }}</div>
        </div>
        <div class="col-lg-2">
            <input type="hidden" name="{{ $field('breakfast_included') }}" value="0">
            <label class="form-label d-none d-lg-block">&nbsp;</label>
            <label class="check-in-switch">
                <input class="form-check-input" name="{{ $field('breakfast_included') }}" type="checkbox" value="1" @checked((bool) old("stays.{$index}.breakfast_included", $stay['breakfast_included'] ?? false))>
                <span>Desayuno</span>
            </label>
        </div>
    </div>

    <div class="border-top mt-3 pt-3" data-stay-guests-section>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-2">
            <div>
                <div class="fw-semibold">Acompanantes</div>
                <div class="text-body-secondary small">El titular ya cuenta como huesped de esta estancia.</div>
            </div>
            <button class="btn btn-outline-primary btn-sm" type="button" data-stay-add-guest>
                <i class="ti ti-user-plus me-1"></i>Agregar huesped
            </button>
        </div>
        @error("stays.{$index}.guests")
            <div class="alert alert-danger py-2">{{ $message }}</div>
        @enderror
        <div class="vstack gap-2" data-stay-guests>
            @foreach ($guestRows as $guestIndex => $guest)
                @include('check-ins.partials.stay-guest-row', [
                    'stayIndex' => $index,
                    'guestIndex' => $guestIndex,
                    'guest' => $guest,
                    'countries' => $countries,
                    'documentTypes' => $documentTypes,
                    'defaultBirthCountry' => $defaultBirthCountry ?? null,
                ])
            @endforeach
        </div>
        <div class="form-hint" data-stay-guest-count></div>
        <template data-stay-guest-template>
            @include('check-ins.partials.stay-guest-row', [
                'stayIndex' => $index,
                'guestIndex' => '__GUEST_INDEX__',
                'guest' => [],
                'countries' => $countries,
                'documentTypes' => $documentTypes,
                'defaultBirthCountry' => $defaultBirthCountry ?? null,
            ])
        </template>
    </div>
</div>
