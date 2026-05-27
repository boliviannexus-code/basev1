<dl class="row mb-0">
    <dt class="col-sm-3">Codigo</dt>
    <dd class="col-sm-9">{{ $branch->code }}</dd>
    <dt class="col-sm-3">Nombre</dt>
    <dd class="col-sm-9">{{ $branch->name }}</dd>
    <dt class="col-sm-3">Liga deportiva</dt>
    <dd class="col-sm-9">{{ $branch->company?->name ?? 'Sin liga deportiva' }}</dd>
    <dt class="col-sm-3">Telefono</dt>
    <dd class="col-sm-9">{{ $branch->phone ?: '-' }}</dd>
    <dt class="col-sm-3">Direccion</dt>
    <dd class="col-sm-9">{{ $branch->address ?: '-' }}</dd>
    <dt class="col-sm-3">Almacenes</dt>
    <dd class="col-sm-9">{{ $branch->warehouses_count ?? $branch->warehouses()->count() }}</dd>
    <dt class="col-sm-3">Estado</dt>
    <dd class="col-sm-9"><span class="badge text-bg-{{ $branch->is_active ? 'success' : 'secondary' }}">{{ $branch->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
    <dt class="col-sm-3">Creado</dt>
    <dd class="col-sm-9">{{ $branch->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
