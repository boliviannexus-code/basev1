<div class="row g-3">
    <div class="col-md-5">
        <label class="form-label" for="{{ $prefix }}-name">Nombre</label>
        <input class="form-control" id="{{ $prefix }}-name" name="name" value="{{ old('name', $category->name) }}" maxlength="160" required>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="{{ $prefix }}-price">Precio predeterminado</label>
        <input class="form-control" id="{{ $prefix }}-price" type="number" name="default_unit_price" value="{{ old('default_unit_price', $category->default_unit_price ?? 0) }}" min="0" step="0.01" required>
    </div>
    <div class="col-md-3">
        <label class="form-label" for="{{ $prefix }}-sort">Orden</label>
        <input class="form-control" id="{{ $prefix }}-sort" type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <label class="form-check">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))>
            <span class="form-check-label">Activa</span>
        </label>
    </div>
</div>
