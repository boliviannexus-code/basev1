<div class="row g-3">
    <div class="col-md-12">
        <label class="form-label" for="modal-branch-company">Liga deportiva</label>
        <select class="form-select" id="modal-branch-company" name="company_id" @disabled(auth()->user()?->company_id)>
            <option value="">Sin liga deportiva</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((int) old('company_id', $branch->company_id ?? auth()->user()?->company_id ?? 0) === $company->id)>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        @if (auth()->user()?->company_id)
            <input type="hidden" name="company_id" value="{{ auth()->user()->company_id }}">
        @endif
        <div class="invalid-feedback" data-error-for="company_id"></div>
    </div>

    <div class="col-md-8">
        <label class="form-label" for="modal-branch-name">Nombre</label>
        <input class="form-control" id="modal-branch-name" name="name" value="{{ old('name', $branch->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="modal-branch-code">Codigo</label>
        <input class="form-control" id="modal-branch-code" name="code" value="{{ old('code', $branch->code ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="code"></div>
    </div>
    <div class="col-md-4">
        <label class="form-label" for="modal-branch-phone">Telefono</label>
        <input class="form-control" id="modal-branch-phone" name="phone" value="{{ old('phone', $branch->phone ?? '') }}">
        <div class="invalid-feedback" data-error-for="phone"></div>
    </div>
    <div class="col-md-8">
        <label class="form-label" for="modal-branch-address">Direccion</label>
        <input class="form-control" id="modal-branch-address" name="address" value="{{ old('address', $branch->address ?? '') }}">
        <div class="invalid-feedback" data-error-for="address"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch my-4">
    <input class="form-check-input" id="modal-branch-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $branch->is_active ?? true))>
    <label class="form-check-label" for="modal-branch-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
