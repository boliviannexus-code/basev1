@php
    $permissionsByModule = $role->permissions->sortBy('name')->groupBy(fn ($permission) => str($permission->name)->before('.')->toString());
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-body-secondary small text-uppercase fw-semibold">Rol</div>
            <div class="h3 mb-1">{{ role_label($role->name) }}</div>
            <div class="text-body-secondary small">{{ $role->name }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-body-secondary small text-uppercase fw-semibold">Usuarios asignados</div>
            <div class="h3 mb-0">{{ $role->users_count }}</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="border rounded p-3 h-100">
            <div class="text-body-secondary small text-uppercase fw-semibold">Permisos activos</div>
            <div class="h3 mb-0">{{ $role->permissions_count }}</div>
        </div>
    </div>
</div>

<div class="mt-3">
    @forelse ($permissionsByModule as $module => $permissions)
        <div class="border rounded p-3 mb-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-semibold">{{ permission_module_label($module) }}</div>
                <span class="badge text-bg-light border text-body">{{ $permissions->count() }}</span>
            </div>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($permissions as $permission)
                    @php
                        $action = str_contains($permission->name, '.') ? explode('.', $permission->name, 2)[1] : $permission->name;
                    @endphp
                    <span class="badge text-bg-secondary">{{ permission_action_label($action) }}</span>
                @endforeach
            </div>
        </div>
    @empty
        <div class="text-body-secondary">Este rol no tiene permisos asignados.</div>
    @endforelse
</div>
