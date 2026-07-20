<div class="row g-3">
    <div class="col-12">
        <label class="form-label" for="role-name">Nombre</label>
        <input class="form-control {{ ($errors ?? null)?->has('name') ? 'is-invalid' : '' }}" id="role-name" name="name" value="{{ old('name', $role->name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="name">{{ ($errors ?? null)?->first('name') }}</div>
    </div>
    <input name="guard_name" type="hidden" value="{{ old('guard_name', $role->guard_name ?? 'web') }}">
    <div class="col-12">
        @include('roles.partials.permission-checkboxes')
    </div>
</div>
