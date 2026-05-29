<dl class="row mb-0">
    <dt class="col-sm-4">Equipo</dt>
    <dd class="col-sm-8">{{ $team->name }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $team->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Fecha de fundacion</dt>
    <dd class="col-sm-8">{{ $team->founded_at?->format('Y-m-d') }}</dd>
    <dt class="col-sm-4">Notas</dt>
    <dd class="col-sm-8">{{ $team->notes ?: '-' }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ $team->is_active ? 'success' : 'secondary' }}">{{ $team->is_active ? 'Activo' : 'Inactivo' }}</span></dd>
    <dt class="col-sm-4">Aprobacion</dt>
    <dd class="col-sm-8">
        @if ($team->pendingUpdateRequest)
            <span class="badge text-bg-warning">Edicion pendiente</span>
            <div class="small text-body-secondary mt-1">Solicitado por {{ $team->pendingUpdateRequest->requester?->name ?? '-' }}</div>
        @else
            <span class="badge text-bg-success">Sin cambios pendientes</span>
        @endif
    </dd>
</dl>
