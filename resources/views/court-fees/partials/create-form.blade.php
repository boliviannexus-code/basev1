<form method="POST" action="{{ route('court-fees.store') }}" data-ajax-form>
    @csrf
    @include('court-fees.partials.fields', ['courtFee' => null])
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('court-fees.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Crear derecho</button>
    </div>
</form>
