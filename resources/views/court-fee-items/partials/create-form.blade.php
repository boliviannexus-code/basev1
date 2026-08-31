<form method="POST" action="{{ route('court-fees.items.store', $courtFee) }}" data-ajax-form>
    @csrf
    <div class="alert alert-info py-2 mb-3"><span class="text-body-secondary">Derecho de cancha:</span> <strong>{{ $courtFee->name }}</strong></div>
    @include('court-fee-items.partials.fields', ['courtFeeItem' => null])
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('court-fees.show', $courtFee) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar ítem</button>
    </div>
</form>
