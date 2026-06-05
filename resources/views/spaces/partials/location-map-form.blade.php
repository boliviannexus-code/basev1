@php
    $location = $space->location;
    $company = auth()->user()?->company;
    $latitude = old('latitude', $location->latitude ?? '');
    $longitude = old('longitude', $location->longitude ?? '');
    $defaultCenters = [
        'la paz' => ['lat' => -16.5000, 'lng' => -68.1500],
        'santa cruz' => ['lat' => -17.7833, 'lng' => -63.1821],
        'cochabamba' => ['lat' => -17.3895, 'lng' => -66.1568],
        'sucre' => ['lat' => -19.0196, 'lng' => -65.2619],
        'tarija' => ['lat' => -21.5355, 'lng' => -64.7296],
    ];
    $companyCity = str($company?->city ?? '')->lower()->ascii()->toString();
    $defaultCenter = collect($defaultCenters)->first(fn ($center, $city) => str_contains($companyCity, $city), ['lat' => -16.2902, 'lng' => -63.5887]);
    $mapCenter = [
        'lat' => filled($latitude) ? (float) $latitude : $defaultCenter['lat'],
        'lng' => filled($longitude) ? (float) $longitude : $defaultCenter['lng'],
    ];
    $hasGoogleMapsKey = filled(config('services.google_maps.key'));
@endphp

<div
    class="space-location-map"
    data-space-location-map
    data-google-maps-key="{{ config('services.google_maps.key') }}"
    data-lat="{{ $mapCenter['lat'] }}"
    data-lng="{{ $mapCenter['lng'] }}"
    data-has-location="{{ filled($latitude) && filled($longitude) ? '1' : '0' }}"
>
    <div class="mb-3">
        <label class="form-label" for="space-location-search">Buscar direccion o lugar</label>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-search"></i></span>
            <input class="form-control" id="space-location-search" type="search" placeholder="Busca la direccion o mueve el pin para ajustar la ubicacion exacta." autocomplete="off" data-location-search>
        </div>
        <div class="form-hint">Busca la direccion o mueve el pin para ajustar la ubicacion exacta.</div>
    </div>

    @unless ($hasGoogleMapsKey)
        <div class="alert alert-warning">
            Configura <code>GOOGLE_MAPS_API_KEY</code> para activar el mapa y Places Autocomplete. Los campos manuales siguen disponibles.
        </div>
    @endunless

    <div class="space-location-map-canvas" data-location-canvas></div>
</div>

<div class="row g-3 mt-2">
    <div class="col-md-4">
        <label class="form-label" for="country">Pais</label>
        <input class="form-control @error('country') is-invalid @enderror" id="country" name="country" value="{{ old('country', $location->country ?? 'Bolivia') }}" required data-location-field="country">
        <div class="invalid-feedback">{{ $errors->first('country') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="state-or-region">Region/departamento</label>
        <input class="form-control @error('state_or_region') is-invalid @enderror" id="state-or-region" name="state_or_region" value="{{ old('state_or_region', $location->state_or_region ?? '') }}" data-location-field="state_or_region">
        <div class="invalid-feedback">{{ $errors->first('state_or_region') }}</div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="city">Ciudad</label>
        <input class="form-control @error('city') is-invalid @enderror" id="city" name="city" value="{{ old('city', $location->city ?? '') }}" required data-location-field="city">
        <div class="invalid-feedback">{{ $errors->first('city') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="zone">Zona/barrio</label>
        <input class="form-control @error('zone_or_neighborhood') is-invalid @enderror" id="zone" name="zone_or_neighborhood" value="{{ old('zone_or_neighborhood', $location->zone_or_neighborhood ?? '') }}" data-location-field="zone_or_neighborhood">
        <div class="invalid-feedback">{{ $errors->first('zone_or_neighborhood') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="address">Direccion</label>
        <input class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address', $location->address_text ?? $location->address ?? '') }}" required data-location-field="address">
        <input type="hidden" name="address_text" value="{{ old('address_text', $location->address_text ?? $location->address ?? '') }}" data-location-field="address_text">
        <div class="invalid-feedback">{{ $errors->first('address') }}</div>
    </div>
    <div class="col-12">
        <label class="form-label" for="reference">Referencia</label>
        <textarea class="form-control @error('reference') is-invalid @enderror" id="reference" name="reference" rows="2" data-location-field="reference">{{ old('reference', $location->reference_text ?? $location->reference ?? '') }}</textarea>
        <input type="hidden" name="reference_text" value="{{ old('reference_text', $location->reference_text ?? $location->reference ?? '') }}" data-location-field="reference_text">
        <div class="invalid-feedback">{{ $errors->first('reference') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="latitude">Latitud</label>
        <input class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" value="{{ $latitude }}" placeholder="-16.5000000" data-location-field="latitude">
        <div class="invalid-feedback">{{ $errors->first('latitude') }}</div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="longitude">Longitud</label>
        <input class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" value="{{ $longitude }}" placeholder="-68.1500000" data-location-field="longitude">
        <div class="invalid-feedback">{{ $errors->first('longitude') }}</div>
    </div>
    <input type="hidden" name="google_place_id" value="{{ old('google_place_id', $location->google_place_id ?? '') }}" data-location-field="google_place_id">
</div>
