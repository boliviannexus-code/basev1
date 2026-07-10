<dl class="row mb-0">
    <dt class="col-sm-4">Torneo</dt>
    <dd class="col-sm-8">{{ $tournament->name }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $tournament->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Gestion</dt>
    <dd class="col-sm-8">{{ $tournament->season?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Division</dt>
    <dd class="col-sm-8">{{ $tournament->division?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Categorias</dt>
    <dd class="col-sm-8">{{ $tournament->categories->pluck('name')->implode(', ') ?: '-' }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8">
        <span class="badge text-bg-{{ sports_status_tone($tournament->status) }}">{{ sports_status_label($tournament->status) }}</span>
        <span class="badge text-bg-{{ $tournament->is_active ? 'success' : 'secondary' }}">{{ $tournament->is_active ? 'Activo' : 'Inactivo' }}</span>
    </dd>
</dl>
