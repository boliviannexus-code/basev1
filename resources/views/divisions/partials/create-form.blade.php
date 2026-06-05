<form method="POST" action="{{ route('divisions.store') }}" data-ajax-form data-refresh-url="{{ route('divisions.index') }}" novalidate>
    @csrf
    @include('divisions.partials.fields', ['division' => null])
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Crear division</button>
    </div>
</form>
