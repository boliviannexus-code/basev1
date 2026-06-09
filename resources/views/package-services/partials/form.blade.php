@csrf
@php
    $iconGroups = [
        'Alojamiento' => [
            'ti ti-home' => 'Casa',
            'ti ti-building-community' => 'Hospedaje',
            'ti ti-bed' => 'Cama',
            'ti ti-door' => 'Habitacion',
            'ti ti-bath' => 'Bano',
        ],
        'Transporte' => [
            'ti ti-car' => 'Auto',
            'ti ti-bus' => 'Bus',
            'ti ti-plane' => 'Avion',
            'ti ti-motorbike' => 'Moto',
            'ti ti-map-pin' => 'Traslado',
        ],
        'Alimentacion' => [
            'ti ti-tools-kitchen-2' => 'Comida',
            'ti ti-soup' => 'Cena',
            'ti ti-coffee' => 'Cafe',
            'ti ti-glass' => 'Bebidas',
            'ti ti-cake' => 'Postre',
        ],
        'Tours y aventura' => [
            'ti ti-map' => 'Tour',
            'ti ti-mountain' => 'Montana',
            'ti ti-trekking' => 'Caminata',
            'ti ti-bike' => 'Bicicleta',
            'ti ti-compass' => 'Aventura',
        ],
        'Bienestar y decoracion' => [
            'ti ti-spa' => 'Spa',
            'ti ti-heart' => 'Romantico',
            'ti ti-sparkles' => 'Decoracion',
            'ti ti-candle' => 'Velas',
            'ti ti-flower' => 'Flores',
        ],
        'Equipamiento' => [
            'ti ti-wifi' => 'Wifi',
            'ti ti-device-tv' => 'TV',
            'ti ti-snowflake' => 'Clima',
            'ti ti-backpack' => 'Equipo',
            'ti ti-shield-check' => 'Seguro',
        ],
    ];
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="name">Nombre</label>
        <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $service->name) }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="type">Tipo</label>
        <select class="form-select @error('type') is-invalid @enderror" id="type" name="type">
            <option value="">Sin tipo</option>
            @foreach ($types as $type)
                <option value="{{ $type }}" @selected(old('type', $service->type) === $type)>{{ str($type)->headline() }}</option>
            @endforeach
        </select>
        @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="icon">Icono</label>
        <div class="input-group">
            <span class="input-group-text package-icon-preview" data-package-icon-preview>
                <i class="{{ old('icon', $service->icon) ?: 'ti ti-icons' }}"></i>
            </span>
            <input class="form-control @error('icon') is-invalid @enderror" id="icon" name="icon" value="{{ old('icon', $service->icon) }}" placeholder="ti ti-soup" data-package-icon-input>
            <button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#packageServiceIconModal">
                Elegir
            </button>
        </div>
        @error('icon')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-hint">Puedes escribir la clase o elegir un icono de la lista.</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="description">Descripcion</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $service->description) }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort_order">Orden</label>
        <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $service->sort_order ?? 0) }}">
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3 d-flex align-items-end">
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $service->is_active ?? true))>
            <span class="form-check-label">Activo</span>
        </label>
    </div>
</div>

<div class="mt-4 d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('package-services.index') }}">Cancelar</a>
    <button class="btn btn-primary" type="submit">Guardar servicio</button>
</div>

<div class="modal modal-blur fade" id="packageServiceIconModal" tabindex="-1" aria-labelledby="packageServiceIconModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="packageServiceIconModalTitle">Elegir icono</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @foreach ($iconGroups as $group => $icons)
                    <section class="package-icon-section">
                        <h3>{{ $group }}</h3>
                        <div class="package-icon-grid">
                            @foreach ($icons as $icon => $label)
                                <button class="package-icon-option" type="button" data-package-icon-option="{{ $icon }}" data-bs-dismiss="modal">
                                    <i class="{{ $icon }}"></i>
                                    <span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
            </div>
        </div>
    </div>
</div>
