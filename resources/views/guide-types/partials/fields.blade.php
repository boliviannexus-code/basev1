<div class="mb-3">
    <label class="form-label" for="guide-type-title">Titulo</label>
    <input class="form-control" id="guide-type-title" name="title" value="{{ old('title', $guideType->title ?? '') }}" required>
    <div class="invalid-feedback" data-error-for="title"></div>
</div>

<div class="mb-3">
    <label class="form-label" for="guide-type-description">Descripcion</label>
    <textarea class="form-control" id="guide-type-description" name="description" rows="4">{{ old('description', $guideType->description ?? '') }}</textarea>
    <div class="invalid-feedback" data-error-for="description"></div>
</div>
