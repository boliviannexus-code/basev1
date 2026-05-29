<dl class="row mb-0">
    <dt class="col-sm-3">Titulo</dt>
    <dd class="col-sm-9">{{ $guideType->title }}</dd>

    <dt class="col-sm-3">Descripcion</dt>
    <dd class="col-sm-9">{{ $guideType->description ?: 'Sin descripcion' }}</dd>

    <dt class="col-sm-3">Creado</dt>
    <dd class="col-sm-9">{{ $guideType->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
