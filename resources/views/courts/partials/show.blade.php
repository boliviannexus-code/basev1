<dl class="row mb-0">
    <dt class="col-sm-4">Cancha</dt>
    <dd class="col-sm-8">{{ $court->name }}</dd>

    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $court->company?->name ?? '-' }}</dd>

    <dt class="col-sm-4">Direccion</dt>
    <dd class="col-sm-8">{{ $court->address ?: '-' }}</dd>

    <dt class="col-sm-4">Descripcion</dt>
    <dd class="col-sm-8">{{ $court->description ?: '-' }}</dd>

    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8">
        <span class="badge text-bg-{{ $court->is_active ? 'success' : 'secondary' }}">
            {{ $court->is_active ? 'Activa' : 'Inactiva' }}
        </span>
    </dd>
</dl>
