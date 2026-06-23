@php
    $selectedPermissions = collect(old('permissions', isset($role) ? $role->permissions->pluck('name')->all() : []));
    $permissionCount = $permissionGroups->flatten()->count();
@endphp

<div class="permission-manager" data-permission-manager>
    <div class="permission-manager-toolbar">
        <div>
            <div class="fw-semibold">Accesos del rol</div>
            <div class="text-body-secondary small">
                Selecciona exactamente qué menús y acciones estarán disponibles.
                <span data-permission-selected-count>{{ $selectedPermissions->count() }}</span> de {{ $permissionCount }} seleccionados.
            </div>
        </div>
        <div class="permission-manager-search">
            <i class="ti ti-search"></i>
            <input class="form-control" type="search" placeholder="Buscar módulo o permiso..." aria-label="Buscar permisos" data-permission-search>
        </div>
    </div>

    <div class="permission-module-list">
        @foreach ($permissionGroups as $module => $permissions)
            <section
                class="permission-module-card"
                data-permission-module
                data-permission-search-text="{{ str(permission_module_label($module).' '.\App\Support\PermissionCatalog::moduleDescription($module).' '.$permissions->map(fn ($permission) => permission_label($permission->name).' '.permission_description($permission->name))->join(' '))->lower() }}"
            >
                <div class="permission-module-header">
                    <div>
                        <h3 class="permission-module-title">{{ permission_module_label($module) }}</h3>
                        <p class="permission-module-description">{{ \App\Support\PermissionCatalog::moduleDescription($module) }}</p>
                    </div>
                    <div class="btn-list">
                        <button class="btn btn-outline-primary btn-sm" type="button" data-permission-group-select>Seleccionar todo</button>
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-permission-group-clear>Limpiar</button>
                    </div>
                </div>

                <div class="permission-option-grid">
                    @foreach ($permissions as $permission)
                        <label class="permission-option" for="permission-{{ $permission->id }}">
                            <input
                                class="form-check-input"
                                id="permission-{{ $permission->id }}"
                                name="permissions[]"
                                type="checkbox"
                                value="{{ $permission->name }}"
                                @checked($selectedPermissions->contains($permission->name))
                            >
                            <span>
                                <span class="permission-option-title">{{ permission_action_label(str($permission->name)->after('.')->toString()) }}</span>
                                <span class="permission-option-description">{{ permission_description($permission->name) }}</span>
                                <code class="permission-option-code">{{ $permission->name }}</code>
                            </span>
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach
    </div>

    <div class="permission-manager-empty d-none" data-permission-empty>
        No se encontraron permisos con ese criterio.
    </div>
</div>

<div class="invalid-feedback d-block" data-error-for="permissions">{{ ($errors ?? null)?->first('permissions') }}</div>
