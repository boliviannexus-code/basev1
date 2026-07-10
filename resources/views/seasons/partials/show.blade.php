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
        <span class="badge text-bg-{{ sports_status_tone($season->status) }}">{{ $season->status === 'closed' ? 'Finalizado' : sports_status_label($season->status) }}</span>
    </dd>
</dl>
