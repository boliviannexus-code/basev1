<dl class="row mb-0">
    <dt class="col-sm-3">Titulo</dt>
    <dd class="col-sm-9">{{ $activityType->title }}</dd>

    <dt class="col-sm-3">Slug</dt>
    <dd class="col-sm-9">{{ $activityType->slug }}</dd>

    <dt class="col-sm-3">Icono</dt>
    <dd class="col-sm-9">
        @if ($activityType->icon)
            <i class="ti {{ $activityType->icon }}"></i> {{ $activityType->icon }}
        @else
            -
        @endif
    </dd>

    <dt class="col-sm-3">Descripcion</dt>
    <dd class="col-sm-9">{{ $activityType->description ?: 'Sin descripcion' }}</dd>

    <dt class="col-sm-3">Estado</dt>
    <dd class="col-sm-9">
        <span class="badge text-bg-{{ $activityType->is_active ? 'success' : 'secondary' }}">
            {{ $activityType->is_active ? 'Activo' : 'Inactivo' }}
        </span>
    </dd>

    <dt class="col-sm-3">Creado</dt>
    <dd class="col-sm-9">{{ $activityType->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
