<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label" for="company-name">Nombre de la liga deportiva</label>
        <input class="form-control" id="company-name" name="name" value="{{ old('name', $company->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company-phone">Telefono</label>
        <input class="form-control" id="company-phone" name="phone" value="{{ old('phone', $company->phone ?? '') }}">
        <div class="invalid-feedback" data-error-for="phone"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company-email">Email</label>
        <input class="form-control" id="company-email" name="email" type="email" value="{{ old('email', $company->email ?? '') }}">
        <div class="invalid-feedback" data-error-for="email"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="company-address">Direccion de sede</label>
        <input class="form-control" id="company-address" name="address" value="{{ old('address', $company->address ?? '') }}">
        <div class="invalid-feedback" data-error-for="address"></div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="company-city">Ciudad</label>
        <input class="form-control" id="company-city" name="city" value="{{ old('city', $company->city ?? '') }}">
        <div class="invalid-feedback" data-error-for="city"></div>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="company-country">Pais</label>
        <input class="form-control" id="company-country" name="country" value="{{ old('country', $company->country ?? 'Bolivia') }}">
        <div class="invalid-feedback" data-error-for="country"></div>
    </div>
    <div class="col-md-12">
        <label class="form-label" for="company-logo">Logo de la liga para reportes</label>
        <input class="form-control" id="company-logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp">
        <div class="form-hint">JPG, PNG o WebP. Maximo 2 MB.</div>
        <div class="invalid-feedback" data-error-for="logo"></div>
        @if (($company ?? null)?->logo_url)
            <div class="d-flex align-items-center gap-3 mt-2">
                <img class="avatar avatar-lg" src="{{ $company->logo_url }}" alt="{{ $company->name }}">
                <label class="form-check m-0">
                    <input class="form-check-input" name="remove_logo" type="checkbox" value="1" @checked(old('remove_logo'))>
                    <span class="form-check-label">Quitar logo actual</span>
                </label>
            </div>
            <div class="invalid-feedback d-block" data-error-for="remove_logo"></div>
        @endif
    </div>
    <div class="col-md-12">
        <label class="form-label" for="company-report-footer">Pie de reporte</label>
        <textarea class="form-control" id="company-report-footer" name="report_footer" rows="3">{{ old('report_footer', $company->report_footer ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="report_footer"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="company-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $company->is_active ?? true))>
    <label class="form-check-label" for="company-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
