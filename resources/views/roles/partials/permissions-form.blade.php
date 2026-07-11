<form method="POST" action="{{ route('roles.permissions', $role) }}" data-ajax-form data-refresh-url="{{ route('roles.index') }}" novalidate>
    @csrf
    @method('PATCH')
    <div class="mb-3">
        <div class="text-body-secondary small text-uppercase fw-semibold">Rol seleccionado</div>
        <div class="h3 mb-0">{{ role_label($role->name) }}</div>
    </div>
    @include('roles.partials.permission-checkboxes')
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar permisos</button>
    </div>
</form>
