<dl class="row mb-0">
    <dt class="col-sm-3">Codigo</dt>
    <dd class="col-sm-9">{{ $warehouse->code }}</dd>
    <dt class="col-sm-3">Nombre</dt>
    <dd class="col-sm-9">{{ $warehouse->name }}</dd>
    <dt class="col-sm-3">Liga deportiva</dt>
    <dd class="col-sm-9">{{ $warehouse->company?->name ?? 'Sin liga deportiva' }}</dd>
    <dt class="col-sm-3">Sucursal</dt>
    <dd class="col-sm-9">{{ $warehouse->branch?->name ?? '-' }}</dd>
    <dt class="col-sm-3">Estado</dt>
    <dd class="col-sm-9"><span class="badge text-bg-{{ $warehouse->is_active ? 'success' : 'secondary' }}">{{ $warehouse->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
    <dt class="col-sm-3">Creado</dt>
    <dd class="col-sm-9">{{ $warehouse->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
