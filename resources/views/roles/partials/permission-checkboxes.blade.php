@php
    $selectedPermissions = collect(old('permissions', isset($role) ? $role->permissions->pluck('name')->all() : []));
    $isSuperAdmin = isset($role) && $role->name === 'super_admin';
    $permissionTotal = $permissionGroups->flatten()->count();
    $selectedTotal = $isSuperAdmin ? $permissionTotal : $selectedPermissions->count();
    $permissionActions = [
        'view' => ['tone' => 'primary', 'icon' => 'ti-eye'],
        'create' => ['tone' => 'success', 'icon' => 'ti-plus'],
        'edit' => ['tone' => 'warning', 'icon' => 'ti-pencil'],
        'update' => ['tone' => 'warning', 'icon' => 'ti-pencil'],
        'delete' => ['tone' => 'danger', 'icon' => 'ti-trash'],
        'restore' => ['tone' => 'secondary', 'icon' => 'ti-restore'],
        'generate' => ['tone' => 'info', 'icon' => 'ti-tournament'],
        'review' => ['tone' => 'info', 'icon' => 'ti-checkup-list'],
        'settings' => ['tone' => 'secondary', 'icon' => 'ti-settings'],
        'adjust' => ['tone' => 'warning', 'icon' => 'ti-scale'],
        'reopen' => ['tone' => 'danger', 'icon' => 'ti-lock-open'],
    ];
@endphp

<div class="border rounded p-3" data-permission-manager>
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
        <div>
            <div class="fw-semibold">Permisos del rol</div>
            <div class="text-body-secondary small">
                <span data-permission-selected-count>{{ $selectedTotal }}</span> de {{ $permissionTotal }} permisos seleccionados
            </div>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2">
            <div class="input-icon">
                <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                <input class="form-control" type="search" placeholder="Buscar permiso" aria-label="Buscar permiso" data-permission-search>
            </div>
            @unless ($isSuperAdmin)
                <button class="btn btn-outline-secondary" type="button" data-permission-clear>
                    <i class="ti ti-eraser me-1"></i>
                    Limpiar
                </button>
            @endunless
        </div>
    </div>

    @if ($isSuperAdmin)
        <div class="alert alert-info mb-3">
            El rol super administrador conserva todos los permisos del sistema automaticamente.
        </div>
    @endif

    <div class="row g-3">
        @foreach ($permissionGroups as $module => $permissions)
            @php
                $groupId = 'permissions-'.str($module)->slug();
                $groupSelected = $permissions->filter(fn ($permission) => $isSuperAdmin || $selectedPermissions->contains($permission->name))->count();
            @endphp
            <div class="col-12" data-permission-group data-group-label="{{ str(permission_module_label($module))->lower()->toString() }}">
                <div class="border rounded">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-2 p-3 border-bottom bg-light">
                        <button class="btn btn-link text-start text-decoration-none p-0 fw-semibold text-body" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $groupId }}" aria-expanded="true" aria-controls="{{ $groupId }}">
                            <i class="ti ti-folder me-1"></i>
                            {{ permission_module_label($module) }}
                            <span class="badge text-bg-light border text-body ms-2" data-permission-group-count>{{ $groupSelected }}/{{ $permissions->count() }}</span>
                        </button>
                        @unless ($isSuperAdmin)
                            <div class="btn-list">
                                <button class="btn btn-outline-primary btn-sm" type="button" data-permission-group-check>
                                    <i class="ti ti-checks me-1"></i>
                                    Todo
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" type="button" data-permission-group-uncheck>
                                    <i class="ti ti-minus me-1"></i>
                                    Ninguno
                                </button>
                            </div>
                        @endunless
                    </div>
                    <div class="collapse show" id="{{ $groupId }}">
                        <div class="p-3">
                            <div class="row g-2">
                                @foreach ($permissions as $permission)
                                    @php
                                        [$permissionModule, $action] = str_contains($permission->name, '.')
                                            ? explode('.', $permission->name, 2)
                                            : [$module, $permission->name];
                                        $meta = $permissionActions[$action] ?? ['tone' => 'secondary', 'icon' => 'ti-circle'];
                                        $checked = $isSuperAdmin || $selectedPermissions->contains($permission->name);
                                    @endphp
                                    <div class="col-md-6 col-xl-4" data-permission-item data-permission-label="{{ str(permission_label($permission->name).' '.$permission->name)->lower()->toString() }}">
                                        <label class="permission-option d-flex align-items-start gap-2 border rounded p-2 h-100 {{ $checked ? 'bg-primary-lt border-primary' : 'bg-white' }}">
                                            <input
                                                class="form-check-input mt-1"
                                                id="permission-{{ $permission->id }}"
                                                name="permissions[]"
                                                type="checkbox"
                                                value="{{ $permission->name }}"
                                                data-permission-checkbox
                                                @checked($checked)
                                                @disabled($isSuperAdmin)
                                            >
                                            @if ($isSuperAdmin)
                                                <input name="permissions[]" type="hidden" value="{{ $permission->name }}">
                                            @endif
                                            <span class="flex-fill">
                                                <span class="badge text-bg-{{ $meta['tone'] }} mb-1">
                                                    <i class="ti {{ $meta['icon'] }} me-1"></i>
                                                    {{ permission_action_label($action) }}
                                                </span>
                                                <span class="d-block fw-semibold">{{ permission_module_label($permissionModule) }}</span>
                                                <span class="d-block text-body-secondary small">{{ $permission->name }}</span>
                                            </span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
<div class="invalid-feedback d-block" data-error-for="permissions">{{ ($errors ?? null)?->first('permissions') }}</div>
