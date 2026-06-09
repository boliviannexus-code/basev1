@php
    $resourceLabel = $resource_name ?? null;
    $targetLabel = $targetType === 'stay'
        ? 'Estancia #'.$target->id
        : 'Reserva '.$target->code;
@endphp

<form method="POST" action="{{ $action }}" data-extra-charge-form autocomplete="off">
    @csrf

    <div class="mb-3">
        <div class="text-body-secondary small">{{ $targetType === 'stay' ? 'Estancia ocupada' : 'Reserva' }}</div>
        <div class="fw-semibold">{{ $targetLabel }}</div>
        @if ($resourceLabel)
            <div class="text-body-secondary small">{{ $resourceLabel }}</div>
        @endif
    </div>

    @if ($categories->isEmpty())
        <div class="alert alert-warning mb-0">
            No hay categorias activas de cargos extras para esta empresa.
        </div>
    @else
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="extra-charge-category">Categoria</label>
                <select class="form-select" id="extra-charge-category" name="extra_charge_category_id" data-extra-charge-category required>
                    @foreach ($categories as $category)
                        <option
                            value="{{ $category->id }}"
                            data-unit-price="{{ $category->default_unit_price }}"
                        >
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="extra-charge-detail">Detalle</label>
                <input class="form-control" id="extra-charge-detail" name="detail" maxlength="255" placeholder="Detalle del cargo">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="extra-charge-date">Fecha</label>
                <input class="form-control" id="extra-charge-date" type="date" name="date" value="{{ now()->toDateString() }}">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="extra-charge-unit">Unitario</label>
                <div class="input-group">
                    <input class="form-control" id="extra-charge-unit" type="number" name="unit_price" min="0" step="0.5" data-extra-charge-unit required>
                    <span class="input-group-text">Bs</span>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="extra-charge-quantity">Cantidad</label>
                <input class="form-control" id="extra-charge-quantity" type="number" name="quantity" min="0.5" step="0.5" value="1" data-extra-charge-quantity required>
            </div>
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center border rounded px-3 py-2">
                    <span class="text-body-secondary">Total</span>
                    <strong><span data-extra-charge-total>0.00</span> Bs</strong>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-plus me-1"></i>Registrar cargo
            </button>
        </div>
    @endif
</form>
