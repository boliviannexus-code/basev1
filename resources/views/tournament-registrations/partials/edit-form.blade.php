<form method="POST" action="{{ route('tournament-registrations.update', $registration) }}" data-ajax-form data-refresh-url="{{ route('tournament-registrations.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('tournament-registrations.partials.fields', compact('registration'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
