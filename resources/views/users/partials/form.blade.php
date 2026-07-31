@php
    $selectedRoles = collect(old('roles', isset($user) ? $user->roles->pluck('name')->all() : []));
    $isCreate = ($mode ?? 'create') === 'create';
    $canAssignNoCompany = \App\Support\CompanyContext::canAssignNoCompany(auth()->user());
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="user-name">Nombre</label>
        <input class="form-control {{ ($errors ?? null)?->has('name') ? 'is-invalid' : '' }}" id="user-name" name="name" value="{{ old('name', $user->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name">{{ ($errors ?? null)?->first('name') }}</div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="user-email">Email</label>
        <input class="form-control {{ ($errors ?? null)?->has('email') ? 'is-invalid' : '' }}" id="user-email" name="email" type="email" value="{{ old('email', $user->email ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="email">{{ ($errors ?? null)?->first('email') }}</div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="user-company">Empresa</label>
        <select class="form-select {{ ($errors ?? null)?->has('company_id') ? 'is-invalid' : '' }}" id="user-company" name="company_id" data-tom-select data-placeholder="Seleccionar empresa">
            @if ($canAssignNoCompany)
                <option value="">Sin empresa</option>
            @endif
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((int) old('company_id', $user->company_id ?? 0) === $company->id)>
                    {{ $company->name }}
                </option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="company_id">{{ ($errors ?? null)?->first('company_id') }}</div>
    </div>

    @if ($isCreate)
        <div class="col-md-3">
            <label class="form-label" for="user-password">Contraseña</label>
            <input class="form-control {{ ($errors ?? null)?->has('password') ? 'is-invalid' : '' }}" id="user-password" name="password" type="password" required>
            <div class="invalid-feedback" data-error-for="password">{{ ($errors ?? null)?->first('password') }}</div>
        </div>

        <div class="col-md-3">
            <label class="form-label" for="user-password-confirmation">Confirmar contraseña</label>
            <input class="form-control" id="user-password-confirmation" name="password_confirmation" type="password" required>
            <div class="invalid-feedback" data-error-for="password_confirmation"></div>
        </div>
    @endif

    <div class="col-md-3">
        <label class="form-label" for="user-transaction-pin">Codigo de caja</label>
        <input class="form-control {{ ($errors ?? null)?->has('transaction_pin') ? 'is-invalid' : '' }}" id="user-transaction-pin" name="transaction_pin" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password">
        <div class="form-hint">{{ $isCreate ? 'Opcional. 4 digitos para validar transacciones.' : 'Dejar vacio para conservarlo.' }}</div>
        <div class="invalid-feedback" data-error-for="transaction_pin">{{ ($errors ?? null)?->first('transaction_pin') }}</div>
    </div>

    <div class="col-md-3">
        <label class="form-label" for="user-transaction-pin-confirmation">Confirmar codigo</label>
        <input class="form-control" id="user-transaction-pin-confirmation" name="transaction_pin_confirmation" type="password" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" autocomplete="new-password">
        <div class="invalid-feedback" data-error-for="transaction_pin_confirmation"></div>
    </div>

    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" id="user-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $user->is_active ?? true))>
            <label class="form-check-label" for="user-is-active">Usuario activo</label>
            <div class="invalid-feedback d-block" data-error-for="is_active">{{ ($errors ?? null)?->first('is_active') }}</div>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">Roles</label>
        <div class="row g-2">
            @foreach ($roles as $role)
                <div class="col-md-4">
                    <div class="form-check border rounded p-2 ps-5 bg-light">
                        <input class="form-check-input" id="role-{{ $role->id }}" name="roles[]" type="checkbox" value="{{ $role->name }}" @checked($selectedRoles->contains($role->name))>
                        <label class="form-check-label" for="role-{{ $role->id }}">{{ role_label($role->name) }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="invalid-feedback d-block" data-error-for="roles">{{ ($errors ?? null)?->first('roles') }}</div>
    </div>
</div>
