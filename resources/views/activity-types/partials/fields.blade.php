<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="activity-type-title">Titulo</label>
        <input class="form-control" id="activity-type-title" name="title" value="{{ old('title', $activityType->title ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="title"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="activity-type-slug">Slug</label>
        <input class="form-control" id="activity-type-slug" name="slug" value="{{ old('slug', $activityType->slug ?? '') }}" placeholder="Automatico si se deja vacio">
        <div class="invalid-feedback" data-error-for="slug"></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="activity-type-icon">Icono</label>
        <select class="form-select" id="activity-type-icon" name="icon">
            <option value="">Sin icono</option>
            @foreach (['ti-bus' => 'Transporte', 'ti-tools-kitchen-2' => 'Comida', 'ti-walk' => 'Caminata', 'ti-map-pin' => 'Visita', 'ti-bed' => 'Descanso', 'ti-building-cottage' => 'Alojamiento'] as $icon => $label)
                <option value="{{ $icon }}" @selected(old('icon', $activityType->icon ?? '') === $icon)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="icon"></div>
    </div>

    <div class="col-md-6">
        <input type="hidden" name="is_active" value="0">
        <label class="form-label d-block">Estado</label>
        <label class="form-check form-switch">
            <input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $activityType->is_active ?? true))>
            <span class="form-check-label">Activo</span>
        </label>
        <div class="invalid-feedback d-block" data-error-for="is_active"></div>
    </div>

    <div class="col-12">
        <label class="form-label" for="activity-type-description">Descripcion</label>
        <textarea class="form-control" id="activity-type-description" name="description" rows="4">{{ old('description', $activityType->description ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="description"></div>
    </div>
</div>
