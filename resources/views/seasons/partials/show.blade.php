<dl class="row mb-0">
    <dt class="col-sm-4">Gestion</dt>
    <dd class="col-sm-8">{{ $season->name }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $season->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Anio</dt>
    <dd class="col-sm-8">{{ $season->year ?: '-' }}</dd>
    <dt class="col-sm-4">Torneos</dt>
    <dd class="col-sm-8">{{ $season->tournaments_count }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8">
        <span class="badge text-bg-{{ sports_status_tone($season->status) }}">{{ sports_status_label($season->status) }}</span>
        <span class="badge text-bg-{{ $season->is_active ? 'success' : 'secondary' }}">{{ $season->is_active ? 'Activo' : 'Inactivo' }}</span>
    </dd>
</dl>
