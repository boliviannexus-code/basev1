<form method="POST" action="{{ route('companies.store') }}" enctype="multipart/form-data" data-ajax-form data-refresh-url="{{ route('companies.index') }}" novalidate>
    @csrf
    @include('companies.partials.fields', ['company' => null])
    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Crear liga</button>
    </div>
</form>
