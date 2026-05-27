<dl class="row mb-0">
    <dt class="col-sm-4">Categoria</dt>
    <dd class="col-sm-8">{{ $category->name }}</dd>
    <dt class="col-sm-4">Division</dt>
    <dd class="col-sm-8">{{ $category->division?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Rango de edad</dt>
    <dd class="col-sm-8">{{ $category->division?->min_age }} a {{ $category->division?->max_age }} años</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $category->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Descripcion</dt>
    <dd class="col-sm-8">{{ $category->description ?: '-' }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">{{ $category->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
</dl>
