<dl class="row mb-0">
    <dt class="col-sm-4">Division</dt>
    <dd class="col-sm-8">{{ $division->name }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $division->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Rango de edad</dt>
    <dd class="col-sm-8">{{ $division->min_age }} a {{ $division->max_age }} años</dd>
    <dt class="col-sm-4">Descripcion</dt>
    <dd class="col-sm-8">{{ $division->description ?: '-' }}</dd>
    <dt class="col-sm-4">Categorias</dt>
    <dd class="col-sm-8">
        @forelse ($division->categories as $category)
            <span class="badge text-bg-secondary">{{ $category->name }}</span>
        @empty
            -
        @endforelse
    </dd>
    <dt class="col-sm-4">Torneos vinculados</dt>
    <dd class="col-sm-8">{{ $division->tournaments_count }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ $division->is_active ? 'success' : 'secondary' }}">{{ $division->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
</dl>
