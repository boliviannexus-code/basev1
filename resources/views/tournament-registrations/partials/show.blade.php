<dl class="row mb-0">
    <dt class="col-sm-4">Equipo</dt>
    <dd class="col-sm-8">{{ $registration->team?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Torneo</dt>
    <dd class="col-sm-8">{{ $registration->tournament?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Gestion</dt>
    <dd class="col-sm-8">{{ $registration->tournament?->season?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $registration->company?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Estado</dt>
    <dd class="col-sm-8"><span class="badge text-bg-{{ registration_status_tone($registration->status) }}">{{ registration_status_label($registration->status) }}</span></dd>
    <dt class="col-sm-4">Notas</dt>
    <dd class="col-sm-8">{{ $registration->notes ?: '-' }}</dd>
</dl>
