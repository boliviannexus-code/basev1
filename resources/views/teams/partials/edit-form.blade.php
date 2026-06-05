<form method="POST" action="{{ route('teams.update', $team) }}" data-ajax-form data-refresh-url="{{ route('teams.index') }}" novalidate>
    @csrf
    @method('PUT')
    @if ($team->pendingUpdateRequest)
        <div class="alert alert-warning">
            Este equipo tiene una edicion pendiente de aprobacion. No se podran enviar nuevos cambios hasta que el superadmin la revise.
        </div>
    @elseif (! auth()->user()?->can('teams.approve-updates'))
        <div class="alert alert-info">
            Los cambios quedaran pendientes hasta que un superadmin los apruebe.
        </div>
    @endif
    @include('teams.partials.fields', compact('team'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit" @disabled($team->pendingUpdateRequest && ! auth()->user()?->can('teams.approve-updates'))><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
