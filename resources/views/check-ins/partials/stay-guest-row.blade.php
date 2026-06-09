@php
    $fieldPrefix = $fieldPrefix ?? "stays[{$stayIndex}][guests]";
    $errorPrefixBase = $errorPrefixBase ?? "stays.{$stayIndex}.guests";
    $field = fn (string $name) => "{$fieldPrefix}[{$guestIndex}][{$name}]";
    $errorPrefix = "{$errorPrefixBase}.{$guestIndex}";
    $selectedCountryId = old("{$errorPrefix}.birth_country_id", $guest['birth_country_id'] ?? $defaultBirthCountry?->id ?? '');
    $selectedCountry = $selectedCountryId
        ? $countries->firstWhere('id', (int) $selectedCountryId)
        : null;
@endphp

<div class="border rounded p-2" data-stay-guest-row>
    <input type="hidden" name="{{ $field('id') }}" value="{{ $guest['id'] ?? '' }}" data-stay-guest-id>
    <div class="row g-2 align-items-end">
        <div class="col-md-1">
            <label class="form-label">Tipo doc.</label>
            <select class="form-select @error("{$errorPrefix}.document_type") is-invalid @enderror" name="{{ $field('document_type') }}" data-stay-guest-input data-stay-guest-document-type>
                @foreach ($documentTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old("{$errorPrefix}.document_type", $guest['document_type'] ?? 'passport') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.document_type") }}</div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Documento</label>
            <input class="form-control @error("{$errorPrefix}.document_number") is-invalid @enderror" name="{{ $field('document_number') }}" value="{{ old("{$errorPrefix}.document_number", $guest['document_number'] ?? '') }}" autocomplete="off" data-stay-guest-input data-stay-guest-document-number>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.document_number") }}</div>
            <div class="form-hint d-none" data-stay-guest-lookup-message></div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Nombre</label>
            <input class="form-control @error("{$errorPrefix}.first_name") is-invalid @enderror" name="{{ $field('first_name') }}" value="{{ old("{$errorPrefix}.first_name", $guest['first_name'] ?? '') }}" autocomplete="off" data-stay-guest-input data-stay-guest-first-name>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.first_name") }}</div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Apellido paterno</label>
            <input class="form-control @error("{$errorPrefix}.last_name") is-invalid @enderror" name="{{ $field('last_name') }}" value="{{ old("{$errorPrefix}.last_name", $guest['last_name'] ?? '') }}" autocomplete="off" data-stay-guest-input data-stay-guest-last-name>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.last_name") }}</div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Nacimiento</label>
            <input class="form-control @error("{$errorPrefix}.birth_date") is-invalid @enderror" name="{{ $field('birth_date') }}" type="date" max="{{ today()->toDateString() }}" value="{{ old("{$errorPrefix}.birth_date", $guest['birth_date'] ?? today()->toDateString()) }}" autocomplete="off" data-stay-guest-input data-stay-guest-birth-date>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.birth_date") }}</div>
        </div>
        <div class="col-md-2">
            <label class="form-label">Pais nacimiento</label>
            <select class="form-select @error("{$errorPrefix}.birth_country_id") is-invalid @enderror" name="{{ $field('birth_country_id') }}" data-stay-guest-input data-stay-guest-country data-countries-url="{{ route('countries.autocomplete') }}" data-placeholder="Buscar pais">
                @if ($selectedCountry)
                    <option value="{{ $selectedCountry->id }}" selected>{{ $selectedCountry->name }} ({{ $selectedCountry->iso_code }})</option>
                @endif
            </select>
            <div class="invalid-feedback">{{ $errors->first("{$errorPrefix}.birth_country_id") }}</div>
        </div>
        <div class="col-md-1">
            <button class="btn btn-outline-danger w-100" type="button" data-stay-remove-guest aria-label="Quitar huesped">
                <i class="ti ti-trash"></i>
            </button>
        </div>
    </div>
</div>
