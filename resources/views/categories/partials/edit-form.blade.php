<form method="POST" action="{{ route('categories.update', $category) }}" data-ajax-form data-refresh-url="{{ route('categories.index') }}" novalidate>
    @csrf
    @method('PUT')
    @include('categories.partials.fields', compact('category'))
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('categories.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Guardar cambios</button>
    </div>
</form>
