@if ($companies->count() > 1)
    <div class="mb-3">
        <label class="form-label" for="court-company">Liga deportiva</label>
        <select class="form-select @error('company_id') is-invalid @enderror" id="court-company" name="company_id" required>
            <option value="">Seleccionar liga</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((int) old('company_id', $court?->company_id) === (int) $company->id)>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        @error('company_id')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
@endif

<div class="mb-3">
    <label class="form-label" for="court-name">Nombre</label>
    <input class="form-control @error('name') is-invalid @enderror" id="court-name" name="name" value="{{ old('name', $court?->name) }}" required maxlength="255">
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="court-address">Direccion</label>
    <input class="form-control @error('address') is-invalid @enderror" id="court-address" name="address" value="{{ old('address', $court?->address) }}" maxlength="255">
    @error('address')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<div class="mb-3">
    <label class="form-label" for="court-description">Descripcion</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="court-description" name="description" rows="3" maxlength="1000">{{ old('description', $court?->description) }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

<label class="form-check mb-3">
    <input type="hidden" name="is_active" value="0">
    <input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $court?->is_active ?? true))>
    <span class="form-check-label">Cancha activa</span>
</label>
