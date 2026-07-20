<form method="POST" action="{{ route('transport-types.update', $transportType) }}" data-ajax-form data-refresh-url="{{ route('transport-types.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('transport-types.partials.fields', compact('transportType'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
