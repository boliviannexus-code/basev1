@php
    $canSelectCompany = \App\Support\CompanyContext::id(auth()->user()) === null;
@endphp

<div class="row g-3">
    @if ($canSelectCompany && ! ($tournament ?? null))
        <div class="col-md-12">
            <label class="form-label" for="tournament-company">Liga deportiva</label>
            <select class="form-select" id="tournament-company" name="company_id" data-tom-select data-placeholder="Seleccionar liga deportiva" required>
                <option value="">Seleccionar liga deportiva</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id', $tournament->company_id ?? 0) === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="form-hint">Primero selecciona una gestion de esa misma liga.</div>
            <div class="invalid-feedback" data-error-for="company_id"></div>
        </div>
    @endif

    <div class="col-md-12">
        <label class="form-label" for="tournament-season">Gestion</label>
        <select class="form-select" id="tournament-season" name="season_id" data-tom-select data-placeholder="Seleccionar gestion" required>
            <option value="" disabled @selected((int) old('season_id', $tournament->season_id ?? 0) === 0)>Seleccionar gestion</option>
            @foreach ($seasons as $season)
                <option value="{{ $season->id }}" data-label="{{ $season->name }}" @selected((int) old('season_id', $tournament->season_id ?? 0) === $season->id)>
                    {{ $season->name }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="season_id"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="tournament-division">Division</label>
        <select class="form-select" id="tournament-division" name="division_id" data-tom-select data-placeholder="Seleccionar division" data-tournament-division required>
            <option value="" disabled @selected((int) old('division_id', $tournament->division_id ?? 0) === 0)>Seleccionar division</option>
            @foreach ($divisions as $division)
                <option value="{{ $division->id }}" data-label="{{ $division->name }}" @selected((int) old('division_id', $tournament->division_id ?? 0) === $division->id)>
                    {{ $division->name }} - {{ $division->min_age }} a {{ $division->max_age }} años
                </option>
            @endforeach
        </select>
        <div class="form-hint">La division define el rango de edad base para futuras habilitaciones.</div>
        <div class="invalid-feedback" data-error-for="division_id"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="tournament-category">Categoria</label>
        <select class="form-select" id="tournament-category" name="category_id" data-tom-select data-placeholder="Seleccionar categoria" data-tournament-category required>
            <option value="" disabled @selected((int) old('category_id', $tournament->category_id ?? 0) === 0)>Seleccionar categoria</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" data-division-id="{{ $category->division_id }}" data-label="{{ $category->name }}" @selected((int) old('category_id', $tournament->category_id ?? 0) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="category_id"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="tournament-name-preview">Nombre del torneo</label>
        <input class="form-control" id="tournament-name-preview" value="{{ old('name', $tournament->name ?? '') }}" data-tournament-name-preview readonly>
        <div class="form-hint">Se genera automaticamente con division, categoria y gestion.</div>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-12">
        <label class="form-label" for="tournament-status">Estado</label>
        <select class="form-select" id="tournament-status" name="status" required>
            @foreach (['planned', 'active', 'closed'] as $status)
                <option value="{{ $status }}" @selected(old('status', $tournament->status ?? 'planned') === $status)>{{ sports_status_label($status) }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="status"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="tournament-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $tournament->is_active ?? true))>
    <label class="form-check-label" for="tournament-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
