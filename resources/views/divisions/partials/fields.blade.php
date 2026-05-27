@php
    $canSelectCompany = \App\Support\CompanyContext::id(auth()->user()) === null;
@endphp

<div class="row g-3">
    @if ($canSelectCompany)
        <div class="col-md-12">
            <label class="form-label" for="division-company">Liga deportiva</label>
            <select class="form-select" id="division-company" name="company_id" data-tom-select data-placeholder="Seleccionar liga deportiva" required>
                <option value="">Seleccionar liga deportiva</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id', $division->company_id ?? 0) === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="company_id"></div>
        </div>
    @endif

    <div class="col-md-12">
        <label class="form-label" for="division-name">Nombre de la division</label>
        <input class="form-control" id="division-name" name="name" value="{{ old('name', $division->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="division-min-age">Edad minima</label>
        <input class="form-control" id="division-min-age" name="min_age" type="number" min="0" max="120" value="{{ old('min_age', $division->min_age ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="min_age"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="division-max-age">Edad maxima</label>
        <input class="form-control" id="division-max-age" name="max_age" type="number" min="0" max="120" value="{{ old('max_age', $division->max_age ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="max_age"></div>
    </div>
    <div class="col-md-12">
        <label class="form-label" for="division-description">Descripcion</label>
        <textarea class="form-control" id="division-description" name="description" rows="3">{{ old('description', $division->description ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="description"></div>
    </div>
</div>

@if ($division ?? null)
    @php
        $selectedCategoryIds = collect(old('category_ids', $division->categories->pluck('id')->all()))->map(fn ($id) => (int) $id)->all();
    @endphp
    <input type="hidden" name="sync_categories" value="1">
    <div class="mt-4">
        <label class="form-label" for="division-categories">Categorias</label>
        <select class="form-select" id="division-categories" name="category_ids[]" data-tom-select data-placeholder="Seleccionar categorias" multiple>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(in_array($category->id, $selectedCategoryIds, true))>
                    {{ $category->name }} - {{ $category->division?->name ?? 'Sin division' }}
                </option>
            @endforeach
        </select>
        <div class="form-hint">Las categorias se registran en el modulo Categorias. Aqui solo se asocian o se quitan de esta division.</div>
        <div class="invalid-feedback d-block" data-error-for="category_ids"></div>
    </div>
@endif

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="division-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $division->is_active ?? true))>
    <label class="form-check-label" for="division-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
