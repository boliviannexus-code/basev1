<div class="row g-3">
    @if (\App\Support\CompanyContext::id() === null)
        <div class="col-md-6">
            <label class="form-label" for="tour-company-id">Empresa</label>
            <select class="form-select" id="tour-company-id" name="company_id" required>
                <option value="">Seleccionar empresa</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) old('company_id', $tour->company_id ?? 0) === (int) $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="company_id"></div>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="tour-name">Nombre</label>
        <input class="form-control" id="tour-name" name="name" value="{{ old('name', $tour->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="tour-duration">Duracion</label>
        <input class="form-control" id="tour-duration" name="duration" value="{{ old('duration', $tour->duration ?? '') }}" placeholder="Ej. 3 horas">
        <div class="invalid-feedback" data-error-for="duration"></div>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="tour-status">Estado</label>
        <select class="form-select" id="tour-status" name="status" required>
            @foreach (\App\Models\Tour::STATUSES as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $tour->status ?? \App\Models\Tour::STATUS_ACTIVE) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="status"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tour-meeting-point">Punto de encuentro</label>
        <input class="form-control" id="tour-meeting-point" name="meeting_point" value="{{ old('meeting_point', $tour->meeting_point ?? '') }}">
        <div class="invalid-feedback" data-error-for="meeting_point"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="tour-description">Descripcion</label>
        <textarea class="form-control" id="tour-description" name="description" rows="3">{{ old('description', $tour->description ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="description"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tour-included">Incluye</label>
        <textarea class="form-control" id="tour-included" name="included" rows="4">{{ old('included', $tour->included ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="included"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="tour-not-included">No incluye</label>
        <textarea class="form-control" id="tour-not-included" name="not_included" rows="4">{{ old('not_included', $tour->not_included ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="not_included"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="tour-requirements">Requisitos</label>
        <textarea class="form-control" id="tour-requirements" name="requirements" rows="3">{{ old('requirements', $tour->requirements ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="requirements"></div>
    </div>
</div>
