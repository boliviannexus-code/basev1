<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label" for="category-division">Division</label>
        <select class="form-select" id="category-division" name="division_id" data-tom-select data-placeholder="Seleccionar division" required>
            <option value="">Seleccionar division</option>
            @foreach ($divisions as $division)
                <option value="{{ $division->id }}" @selected((int) old('division_id', $category->division_id ?? 0) === $division->id)>
                    {{ $division->name }} - {{ $division->min_age }} a {{ $division->max_age }} años
                </option>
            @endforeach
        </select>
        <div class="form-hint">La categoria queda disponible para futuros campeonatos que usen esta division.</div>
        <div class="invalid-feedback" data-error-for="division_id"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="category-name">Nombre de la categoria</label>
        <input class="form-control" id="category-name" name="name" value="{{ old('name', $category->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="category-description">Descripcion</label>
        <textarea class="form-control" id="category-description" name="description" rows="3">{{ old('description', $category->description ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="description"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="category-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $category->is_active ?? true))>
    <label class="form-check-label" for="category-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
