<dl class="row mb-0">
    <dt class="col-sm-4">Usuario</dt>
    <dd class="col-sm-8">{{ $template->user?->name ?? '-' }}</dd>
    <dt class="col-sm-4">Email</dt>
    <dd class="col-sm-8">{{ $template->user?->email ?? '-' }}</dd>
    <dt class="col-sm-4">Liga deportiva</dt>
    <dd class="col-sm-8">{{ $template->user?->company?->name ?? 'Sin liga deportiva' }}</dd>
    <dt class="col-sm-4">Formato</dt>
    <dd class="col-sm-8">{{ $template->format ?: '-' }}</dd>
    <dt class="col-sm-4">Tamano plantilla</dt>
    <dd class="col-sm-8">{{ strlen($template->template_data) }} caracteres</dd>
    <dt class="col-sm-4">Registrada</dt>
    <dd class="col-sm-8">{{ $template->created_at?->format('Y-m-d H:i') }}</dd>
</dl>
