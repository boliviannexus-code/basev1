@php
    $flatWarehouses = $warehouses->flatten(1);
    $selectedUsers = collect(old('users', isset($pointOfSale) ? $pointOfSale->users->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
@endphp

<div class="row g-3 point-of-sale-form-grid">
    <div class="col-md-6 col-xl-4">
        <label class="form-label" for="modal-point-of-sale-branch">Sucursal (opcional)</label>
        <select class="form-select" id="modal-point-of-sale-branch" name="branch_id" data-point-sale-branch>
            <option value="">Sin sucursal por ahora</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((int) old('branch_id', $pointOfSale->branch_id ?? 0) === $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="branch_id"></div>
    </div>
    <div class="col-md-6 col-xl-4">
        <label class="form-label" for="modal-point-of-sale-warehouse">Almacen vinculado (opcional)</label>
        <select class="form-select" id="modal-point-of-sale-warehouse" name="warehouse_id" data-point-sale-warehouse>
            <option value="">Sin almacen por ahora</option>
            @foreach ($flatWarehouses as $warehouse)
                <option value="{{ $warehouse->id }}" data-branch-id="{{ $warehouse->branch_id }}" @selected((int) old('warehouse_id', $pointOfSale->warehouse_id ?? 0) === $warehouse->id)>
                    {{ $warehouse->branch?->name }} - {{ $warehouse->name }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="warehouse_id"></div>
    </div>
    <div class="col-md-8 col-xl-4">
        <label class="form-label" for="modal-point-of-sale-name">Nombre</label>
        <input class="form-control" id="modal-point-of-sale-name" name="name" value="{{ old('name', $pointOfSale->name ?? '') }}"  autocomplete="new-password" data-lpignore="true" data-1p-ignore="true"  required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-4 col-xl-3">
        <label class="form-label" for="modal-point-of-sale-code">Codigo</label>
        <input class="form-control" id="modal-point-of-sale-code" value="{{ $pointOfSale->code ?? 'Se generara automaticamente' }}" readonly>
        <div class="invalid-feedback" data-error-for="code"></div>
    </div>
    <div class="col-md-4 col-xl-3">
        <label class="form-label" for="modal-point-of-sale-receipt-prefix">Prefijo comprobante</label>
        <input
            class="form-control"
            id="modal-point-of-sale-receipt-prefix"
            name="receipt_prefix"
            value="{{ old('receipt_prefix', $pointOfSale->receipt_prefix ?? '') }}"
            placeholder="{{ $pointOfSale->code ?? 'Automatico' }}"
            autocomplete="off"
        >
        <div class="invalid-feedback" data-error-for="receipt_prefix"></div>
    </div>
    <div class="col-md-4 col-xl-3">
        <label class="form-label" for="modal-point-of-sale-receipt-next-number">Siguiente numero</label>
        <input
            class="form-control"
            id="modal-point-of-sale-receipt-next-number"
            name="receipt_next_number"
            type="number"
            min="1"
            value="{{ old('receipt_next_number', $pointOfSale->receipt_next_number ?? 1) }}"
        >
        <div class="invalid-feedback" data-error-for="receipt_next_number"></div>
    </div>
    <div class="col-md-4 col-xl-3">
        <label class="form-label" for="modal-point-of-sale-receipt-digits">Digitos</label>
        <input
            class="form-control"
            id="modal-point-of-sale-receipt-digits"
            name="receipt_digits"
            type="number"
            min="1"
            max="12"
            value="{{ old('receipt_digits', $pointOfSale->receipt_digits ?? 6) }}"
        >
        <div class="invalid-feedback" data-error-for="receipt_digits"></div>
    </div>
    <div class="col-12">
        <label class="form-label" for="modal-point-of-sale-users">Usuarios asignados</label>
        <select
            class="form-select"
            id="modal-point-of-sale-users"
            name="users[]"
            multiple
            data-tom-select
            data-placeholder="Seleccionar usuarios"
        >
            @foreach ($users as $user)
                <option value="{{ $user->id }}" @selected($selectedUsers->contains($user->id))>
                    {{ $user->name }} - {{ $user->email }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback d-block" data-error-for="users"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch my-4">
    <input class="form-check-input" id="modal-point-of-sale-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $pointOfSale->is_active ?? true))>
    <label class="form-check-label" for="modal-point-of-sale-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
