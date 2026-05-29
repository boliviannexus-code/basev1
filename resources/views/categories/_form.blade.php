<div class="mb-3">
    <label class="form-label" for="name">Nombre</label>
    <input class="form-control @error('name')  autocomplete="off" is-invalid @enderror" id="name" name="name" value="{{ old('name', $category->name ?? '') }}" required>
    @error('name')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<div class="mb-3">
    <label class="form-label" for="description">Descripcion</label>
    <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="4">{{ old('description', $category->description ?? '') }}</textarea>
    @error('description')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mb-3">
    <input class="form-check-input" id="is_active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category->is_active ?? true))>
    <label class="form-check-label" for="is_active">Activo</label>
</div>
<div class="d-flex gap-2">
    <button class="btn btn-primary" type="submit">Guardar</button>
    <a class="btn btn-outline-secondary" href="{{ route('categories.index') }}">Cancelar</a>
</div>
