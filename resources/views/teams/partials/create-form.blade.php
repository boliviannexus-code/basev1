<form method="POST" action="{{ route('teams.store') }}" data-ajax-form data-refresh-url="{{ route('teams.index') }}" novalidate>
    @csrf
    @include('teams.partials.fields', ['team' => null])
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Registrar equipo</button>
    </div>
</form>
