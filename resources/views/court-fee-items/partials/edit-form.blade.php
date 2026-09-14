<form method="POST" action="{{ route('court-fee-items.update', $courtFeeItem) }}" data-ajax-form>
    @csrf
    @method('PUT')
    <div class="alert alert-info py-2 mb-3"><span class="text-body-secondary">Derecho de cancha:</span> <strong>{{ $courtFeeItem->courtFee->name }}</strong></div>
    @include('court-fee-items.partials.fields')
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('court-fees.show', $courtFeeItem->court_fee_id) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar cambios</button>
    </div>
</form>
