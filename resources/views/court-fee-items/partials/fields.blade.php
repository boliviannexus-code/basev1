<div class="row g-3">
    <div class="col-md-8">
        <label class="form-label" for="court-fee-name">Nombre</label>
        <input class="form-control @error('name') is-invalid @enderror" id="court-fee-name" name="name" value="{{ old('name', $courtFeeItem->name ?? '') }}" maxlength="255" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label" for="court-fee-cost">Costo (Bs)</label>
        <div class="input-group">
            <span class="input-group-text">Bs</span>
            <input class="form-control text-end @error('cost') is-invalid @enderror" id="court-fee-cost" name="cost" type="number" min="0" max="9999999999.99" step="0.01" inputmode="decimal" value="{{ old('cost', $courtFeeItem->cost ?? '') }}" required>
            @error('cost')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="form-hint">Importe con hasta 2 decimales.</div>
    </div>
</div>
