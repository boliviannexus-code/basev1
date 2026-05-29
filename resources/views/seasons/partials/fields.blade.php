@php
    $canSelectCompany = \App\Support\CompanyContext::id(auth()->user()) === null;
@endphp

<div class="row g-3">
    @if ($canSelectCompany)
        <div class="col-md-12">
            <label class="form-label" for="season-company">Liga deportiva</label>
            <select class="form-select" id="season-company" name="company_id" data-tom-select data-placeholder="Seleccionar liga deportiva" required>
                <option value="">Seleccionar liga deportiva</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id', $season->company_id ?? 0) === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="company_id"></div>
        </div>
    @endif

    <div class="col-md-8">
        <label class="form-label" for="season-name">Nombre de la gestion</label>
        <input class="form-control" id="season-name" name="name" value="{{ old('name', $season->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="season-year">Anio</label>
        <input class="form-control" id="season-year" name="year" type="number" min="1900" max="2100" value="{{ old('year', $season->year ?? now()->year) }}">
        <div class="invalid-feedback" data-error-for="year"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="season-status">Estado</label>
        <select class="form-select" id="season-status" name="status" required>
            @foreach (['planned', 'active', 'closed'] as $status)
                <option value="{{ $status }}" @selected(old('status', $season->status ?? 'planned') === $status)>{{ sports_status_label($status) }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="status"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="season-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $season->is_active ?? true))>
    <label class="form-check-label" for="season-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
