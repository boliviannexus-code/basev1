@php
    $defaultCheckIn = now()->toDateString();
    $defaultCheckOut = now()->addDay()->toDateString();
@endphp

<form class="public-search" action="{{ route('public.accommodations.search') }}" method="get" data-public-accommodation-search data-google-maps-key="{{ config('services.google_maps.key') }}">
    <div>
        <label class="form-label" for="destination">Destino</label>
        <div class="input-icon">
            <span class="input-icon-addon"><i class="ti ti-map-pin"></i></span>
            <input class="form-control @error('destination') is-invalid @enderror" id="destination" name="destination" value="{{ old('destination', $filters['destination'] ?? '') }}" placeholder="Ciudad, departamento o region" autocomplete="off" data-public-destination-search>
        </div>
        <input type="hidden" name="destination_latitude" value="{{ old('destination_latitude', $filters['destination_latitude'] ?? '') }}" data-public-destination-field="latitude">
        <input type="hidden" name="destination_longitude" value="{{ old('destination_longitude', $filters['destination_longitude'] ?? '') }}" data-public-destination-field="longitude">
        <input type="hidden" name="destination_city" value="{{ old('destination_city', $filters['destination_city'] ?? '') }}" data-public-destination-field="city">
        <input type="hidden" name="destination_state" value="{{ old('destination_state', $filters['destination_state'] ?? '') }}" data-public-destination-field="state">
        <input type="hidden" name="destination_country" value="{{ old('destination_country', $filters['destination_country'] ?? '') }}" data-public-destination-field="country">
        @error('destination')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div>
        <label class="form-label" for="check_in">Ingreso</label>
        <input class="form-control @error('check_in') is-invalid @enderror" id="check_in" name="check_in" type="date" value="{{ old('check_in', $filters['check_in'] ?? $defaultCheckIn) }}">
        @error('check_in')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div>
        <label class="form-label" for="check_out">Salida</label>
        <input class="form-control @error('check_out') is-invalid @enderror" id="check_out" name="check_out" type="date" value="{{ old('check_out', $filters['check_out'] ?? $defaultCheckOut) }}">
        @error('check_out')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <div>
        <label class="form-label" for="guests">Personas</label>
        <input class="form-control @error('guests') is-invalid @enderror" id="guests" name="guests" type="number" min="1" max="50" value="{{ old('guests', $filters['guests'] ?? 1) }}">
        @error('guests')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>
    <button class="btn btn-dark public-search-button" type="submit">
        <i class="ti ti-search"></i>
        Buscar hospedaje
    </button>
</form>
