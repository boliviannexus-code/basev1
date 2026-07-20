<div class="mb-3">
    <label class="form-label" for="transport-type-title">Titulo</label>
    <input class="form-control" id="transport-type-title" name="title" value="{{ old('title', $transportType->title ?? '') }}" required>
    <div class="invalid-feedback" data-error-for="title"></div>
</div>

<div class="mb-3">
    <label class="form-label" for="transport-type-description">Descripcion</label>
    <textarea class="form-control" id="transport-type-description" name="description" rows="4">{{ old('description', $transportType->description ?? '') }}</textarea>
    <div class="invalid-feedback" data-error-for="description"></div>
</div>
