<form method="POST" action="{{ route('tours.update', $tour) }}" data-ajax-form data-refresh-url="{{ route('tours.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('tours.partials.fields', compact('tour'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
