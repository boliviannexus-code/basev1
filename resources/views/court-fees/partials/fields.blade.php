<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="court-fee-name">Nombre</label>
        <input class="form-control @error('name') is-invalid @enderror" id="court-fee-name" name="name" value="{{ old('name', $courtFee->name ?? '') }}" maxlength="255" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-4">
        <input type="hidden" name="is_active" value="0">
        <label class="form-label d-block">Estado</label>
        <label class="form-check form-switch"><input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $courtFee->is_active ?? true))><span class="form-check-label">Activo</span></label>
    </div>
    <div class="col-12">
        <label class="form-label" for="court-fee-description">Descripción</label>
        <textarea class="form-control @error('description') is-invalid @enderror" id="court-fee-description" name="description" rows="3" maxlength="1000">{{ old('description', $courtFee->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="invalid-feedback" data-error-for="description"></div>
    </div>
</div>
