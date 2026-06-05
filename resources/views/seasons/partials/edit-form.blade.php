<form method="POST" action="{{ route('seasons.update', $season) }}" data-ajax-form data-refresh-url="{{ route('seasons.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('seasons.partials.fields', compact('season'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
