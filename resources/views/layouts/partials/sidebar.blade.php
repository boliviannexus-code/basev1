@php
    $organizationOpen = request()->routeIs('companies.*', 'company.public-profile.*');
    $adminOpen = request()->routeIs('users.*', 'roles.*', 'permissions.*', 'audits.*');
    $globalAdminOpen = request()->routeIs('admin.accommodation-catalogs.*', 'admin.spaces.*');
    $spacesOpen = request()->routeIs('spaces.*', 'availability.*', 'occupancy.*', 'admin.reservations.*', 'accommodation-packages.*', 'package-services.*');
    $cashOpen = request()->routeIs('space-cash.*');
    $configurationOpen = request()->routeIs('countries.*', 'extra-charge-categories.*', 'exchange-rates.*', 'reservation-channels.*', 'reservation-settings.*', 'payment-methods.*');

    $canPublicProfile = auth()->user()?->company_id !== null
        && auth()->user()?->can('company-public-profile.manage');
    $canOrganization = auth()->user()?->can('companies.view') || $canPublicProfile;
    $canSpaces = auth()->user()?->company_id !== null
        && (auth()->user()?->can('spaces.view') || auth()->user()?->can('spaces.create') || auth()->user()?->can('spaces.edit') || auth()->user()?->can('availability.view') || auth()->user()?->can('occupancy.view') || auth()->user()?->can('reservations.view'));
    $canAdmin = auth()->user()?->can('users.view')
        || auth()->user()?->can('roles.view')
        || auth()->user()?->can('permissions.view')
        || auth()->user()?->can('audits.view');
    $canCash = auth()->user()?->company_id !== null
        && (auth()->user()?->can('space-cash.access')
            || auth()->user()?->can('occupancy.manage')
            || auth()->user()?->can('space-cash.view'));
    $canConfiguration = auth()->user()?->company_id !== null
        && (auth()->user()?->can('countries.manage')
            || auth()->user()?->can('extra-charge-categories.manage')
            || auth()->user()?->can('exchange-rates.manage')
            || auth()->user()?->can('reservation-channels.manage')
            || auth()->user()?->can('payment-methods.view')
            || auth()->user()?->can('occupancy.manage'));
    $canGlobalAdmin = \App\Support\CompanyContext::isGlobalAdmin(auth()->user())
        && (auth()->user()?->can(\App\Support\AccommodationCatalogRegistry::PERMISSION) || auth()->user()?->can('spaces.approve'));
    $sidebarCompany = \App\Support\CompanyContext::activeCompany(auth()->user());
    $accommodationCatalogs = \App\Support\AccommodationCatalogRegistry::all();
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
            <button class="btn btn-icon btn-sm ms-auto app-sidebar-brand-toggle" type="button" data-sidebar-toggle aria-label="Replegar menu" title="Replegar menu" aria-controls="adminSidebar" aria-expanded="true">
                <i class="ti ti-layout-sidebar-left-collapse"></i>
            </button>
        </h1>

        <div class="navbar-collapse" id="sidebar-menu">
            <ul class="navbar-nav">
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
                                @if ($canPublicProfile)
                                    <li class="nav-item {{ request()->routeIs('company.public-profile.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('company.public-profile.edit') }}">
                                            <span class="nav-link-icon"><i class="ti ti-world-www"></i></span>
                                            <span class="nav-link-title">Perfil público</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canSpaces)
                    <li class="nav-item app-menu-section {{ $spacesOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $spacesOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-spaces" aria-expanded="{{ $spacesOpen ? 'true' : 'false' }}" aria-controls="menu-spaces">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-home-star"></i></span>
                            <span class="nav-link-title">Espacios</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $spacesOpen ? 'show' : '' }}" id="menu-spaces">
                            <ul class="nav app-submenu">
                                @can('spaces.view')
                                    <li class="nav-item {{ request()->routeIs('spaces.index', 'spaces.show') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('spaces.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-list-details"></i></span>
                                            <span class="nav-link-title">Listado</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('availability.view')
                                    <li class="nav-item {{ request()->routeIs('availability.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('availability.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar-dollar"></i></span>
                                            <span class="nav-link-title">Disponibilidad</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('occupancy.view')
                                    <li class="nav-item {{ request()->routeIs('occupancy.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('occupancy.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar-stats"></i></span>
                                            <span class="nav-link-title">Ocupabilidad</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('reservations.view')
                                    <li class="nav-item {{ request()->routeIs('admin.reservations.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.reservations.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar-check"></i></span>
                                            <span class="nav-link-title">Reservas entrantes</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('spaces.view')
                                    <li class="nav-item {{ request()->routeIs('accommodation-packages.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('accommodation-packages.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-ticket"></i></span>
                                            <span class="nav-link-title">Paquetes todo incluido</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ request()->routeIs('package-services.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('package-services.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-checklist"></i></span>
                                            <span class="nav-link-title">Servicios de paquetes</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canCash)
                    <li class="nav-item app-menu-section {{ $cashOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $cashOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-cash" aria-expanded="{{ $cashOpen ? 'true' : 'false' }}" aria-controls="menu-cash">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-cash-register"></i></span>
                            <span class="nav-link-title">Caja y cobros</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $cashOpen ? 'show' : '' }}" id="menu-cash">
                            <ul class="nav app-submenu">
                                @if (auth()->user()?->can('space-cash.access') || auth()->user()?->can('occupancy.manage'))
                                    <li class="nav-item {{ request()->routeIs('space-cash.index') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('space-cash.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-cash-register"></i></span>
                                            <span class="nav-link-title">Cajas</span>
                                        </a>
                                    </li>
                                @endif
                                @if (auth()->user()?->can('space-cash.view') || auth()->user()?->can('occupancy.manage'))
                                    <li class="nav-item {{ request()->routeIs('space-cash.history', 'space-cash.show') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('space-cash.history') }}">
                                            <span class="nav-link-icon"><i class="ti ti-report-money"></i></span>
                                            <span class="nav-link-title">Historial de cajas</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canConfiguration)
                    <li class="nav-item app-menu-section {{ $configurationOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $configurationOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-configuration" aria-expanded="{{ $configurationOpen ? 'true' : 'false' }}" aria-controls="menu-configuration">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-settings-cog"></i></span>
                            <span class="nav-link-title">Configuracion</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $configurationOpen ? 'show' : '' }}" id="menu-configuration">
                            <ul class="nav app-submenu">
                                @can('countries.manage')
                                    <li class="nav-item {{ request()->routeIs('countries.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('countries.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-world"></i></span>
                                            <span class="nav-link-title">Paises</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('extra-charge-categories.manage')
                                    <li class="nav-item {{ request()->routeIs('extra-charge-categories.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('extra-charge-categories.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-receipt"></i></span>
                                            <span class="nav-link-title">Cargos extras</span>
                                        </a>
                                    </li>
                                @endcan
                                @if (auth()->user()?->can('exchange-rates.manage') || auth()->user()?->can('occupancy.manage'))
                                    <li class="nav-item {{ request()->routeIs('exchange-rates.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('exchange-rates.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-currency-dollar"></i></span>
                                            <span class="nav-link-title">Tipo de cambio</span>
                                        </a>
                                    </li>
                                @endif
                                @can('reservation-channels.manage')
                                    <li class="nav-item {{ request()->routeIs('reservation-channels.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('reservation-channels.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-route"></i></span>
                                            <span class="nav-link-title">Canales de reserva</span>
                                        </a>
                                    </li>
                                @endcan
                                @if (auth()->user()?->can('reservation-settings.manage') || auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'))
                                    <li class="nav-item {{ request()->routeIs('reservation-settings.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('reservation-settings.edit') }}">
                                            <span class="nav-link-icon"><i class="ti ti-percentage"></i></span>
                                            <span class="nav-link-title">Reservas</span>
                                        </a>
                                    </li>
                                @endif
                                @if (auth()->user()?->can('payment-methods.view') || auth()->user()?->can('occupancy.manage'))
                                    <li class="nav-item {{ request()->routeIs('payment-methods.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('payment-methods.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-credit-card"></i></span>
                                            <span class="nav-link-title">Metodos de pago</span>
                                        </a>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canGlobalAdmin)
                    <li class="nav-item app-menu-section {{ $globalAdminOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $globalAdminOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-global-admin" aria-expanded="{{ $globalAdminOpen ? 'true' : 'false' }}" aria-controls="menu-global-admin">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-world-cog"></i></span>
                            <span class="nav-link-title">Administracion Global</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $globalAdminOpen ? 'show' : '' }}" id="menu-global-admin">
                            <ul class="nav app-submenu">
                                @can('spaces.approve')
                                    <li class="nav-item {{ request()->routeIs('admin.spaces.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.spaces.approvals') }}">
                                            <span class="nav-link-icon"><i class="ti ti-home-check"></i></span>
                                            <span class="nav-link-title">Alojamientos por aprobar</span>
                                        </a>
                                    </li>
                                @endcan
                                @foreach ($accommodationCatalogs as $catalogKey => $catalog)
                                    <li class="nav-item {{ request()->routeIs('admin.accommodation-catalogs.*') && request()->route('catalog') === $catalogKey ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('admin.accommodation-catalogs.index', $catalogKey) }}">
                                            <span class="nav-link-icon"><i class="ti ti-list-details"></i></span>
                                            <span class="nav-link-title">{{ $catalog['label'] }}</span>
                                        </a>
                                    </li>
                                @endforeach
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
