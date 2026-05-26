@php
    $organizationOpen = request()->routeIs('companies.*', 'tours.*', 'bookings.*');
    $catalogOpen = request()->routeIs('categories.*', 'guide-types.*', 'transport-types.*', 'activity-types.*', 'website-settings.*');
    $adminOpen = request()->routeIs('users.*', 'roles.*', 'permissions.*', 'audits.*');

    $canOrganization = auth()->user()?->can('companies.view')
        || auth()->user()?->can('tours.view')
        || auth()->user()?->can('tours.availability')
        || auth()->user()?->can('bookings.view');
    $canCatalog = auth()->user()?->can('categories.view')
        || auth()->user()?->can('guide_types.view')
        || auth()->user()?->can('transport_types.view')
        || auth()->user()?->can('activity_types.view')
        || auth()->user()?->can('website.manage');
    $canAdmin = auth()->user()?->can('users.view')
        || auth()->user()?->can('roles.view')
        || auth()->user()?->can('permissions.view')
        || auth()->user()?->can('audits.view');
    $sidebarCompany = \App\Support\CompanyContext::activeCompany(auth()->user());
@endphp

<aside class="navbar navbar-vertical navbar-expand-lg app-sidebar" id="adminSidebar" data-bs-theme="dark">
    <div class="container-fluid">
        <h1 class="navbar-brand navbar-brand-autodark justify-content-start">
            <a href="{{ route('dashboard') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <span class="lh-sm">
                    <span class="d-block text-truncate">{{ $sidebarCompany?->name ?? config('app.name', 'Base Admin') }}</span>
                    @if ($sidebarCompany)
                        <span class="d-block small text-muted">{{ config('app.name', 'Base Admin') }}</span>
                    @endif
                </span>
            </a>
        </h1>

        <div class="navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav pt-lg-3">
                <li class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('dashboard') }}">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-dashboard"></i></span>
                        <span class="nav-link-title">Dashboard</span>
                    </a>
                </li>

                @if ($canOrganization)
                    <li class="nav-item app-menu-section {{ $organizationOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $organizationOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-organization" aria-expanded="{{ $organizationOpen ? 'true' : 'false' }}" aria-controls="menu-organization">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-building-store"></i></span>
                            <span class="nav-link-title">Organizacion</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $organizationOpen ? 'show' : '' }}" id="menu-organization">
                            <ul class="nav app-submenu">
                                @can('companies.view')
                                    <li class="nav-item {{ request()->routeIs('companies.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('companies.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-building"></i></span>
                                            <span class="nav-link-title">Empresas</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('tours.view')
                                    <li class="nav-item {{ request()->routeIs('tours.index', 'tours.create', 'tours.show', 'tours.wizard.*', 'tours.pricing.*', 'tours.reviews.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('tours.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-map-2"></i></span>
                                            <span class="nav-link-title">Tours</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('tours.availability')
                                    <li class="nav-item {{ request()->routeIs('tours.availability.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('tours.availability.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar-stats"></i></span>
                                            <span class="nav-link-title">Disponibilidad</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('bookings.view')
                                    <li class="nav-item {{ request()->routeIs('bookings.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('bookings.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-ticket"></i></span>
                                            <span class="nav-link-title">Reservas</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canCatalog)
                    <li class="nav-item app-menu-section {{ $catalogOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $catalogOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-catalog" aria-expanded="{{ $catalogOpen ? 'true' : 'false' }}" aria-controls="menu-catalog">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-package"></i></span>
                            <span class="nav-link-title">Catalogo</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $catalogOpen ? 'show' : '' }}" id="menu-catalog">
                            <ul class="nav app-submenu">
                                @can('categories.view')
                                    <li class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('categories.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-category"></i></span>
                                            <span class="nav-link-title">Categorias</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('guide_types.view')
                                    <li class="nav-item {{ request()->routeIs('guide-types.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('guide-types.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-id-badge-2"></i></span>
                                            <span class="nav-link-title">Tipos de guia</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('transport_types.view')
                                    <li class="nav-item {{ request()->routeIs('transport-types.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('transport-types.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-bus"></i></span>
                                            <span class="nav-link-title">Tipos de transporte</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('activity_types.view')
                                    <li class="nav-item {{ request()->routeIs('activity-types.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('activity-types.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-route"></i></span>
                                            <span class="nav-link-title">Tipos de actividad</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('website.manage')
                                    <li class="nav-item {{ request()->routeIs('website-settings.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('website-settings.edit') }}">
                                            <span class="nav-link-icon"><i class="ti ti-world-cog"></i></span>
                                            <span class="nav-link-title">Pagina web</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canAdmin)
                    <li class="nav-item app-menu-section {{ $adminOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $adminOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-admin" aria-expanded="{{ $adminOpen ? 'true' : 'false' }}" aria-controls="menu-admin">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings"></i></span>
                            <span class="nav-link-title">Administracion</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $adminOpen ? 'show' : '' }}" id="menu-admin">
                            <ul class="nav app-submenu">
                                @can('users.view')
                                    <li class="nav-item {{ request()->routeIs('users.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('users.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-users"></i></span>
                                            <span class="nav-link-title">Usuarios</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('roles.view')
                                    <li class="nav-item {{ request()->routeIs('roles.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('roles.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-lock-access"></i></span>
                                            <span class="nav-link-title">Roles</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('permissions.view')
                                    <li class="nav-item {{ request()->routeIs('permissions.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('permissions.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-shield-check"></i></span>
                                            <span class="nav-link-title">Permisos</span>
                                        </a>
                                    </li>
                                @endcan

                                @can('audits.view')
                                    <li class="nav-item {{ request()->routeIs('audits.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('audits.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-list-search"></i></span>
                                            <span class="nav-link-title">Auditoria</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

            </ul>
        </div>
    </div>
</aside>
