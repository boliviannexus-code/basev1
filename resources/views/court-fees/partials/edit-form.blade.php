<form method="POST" action="{{ route('court-fees.update', $courtFee) }}" data-ajax-form>
    @csrf
    @method('PUT')
    @include('court-fees.partials.fields')
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('court-fees.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </div>
</form>
