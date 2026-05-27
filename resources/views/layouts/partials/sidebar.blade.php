@php
    $organizationOpen = request()->routeIs('companies.*', 'seasons.*', 'divisions.*', 'teams.*', 'categories.*');
    $tournamentOpen = request()->routeIs('tournaments.*', 'tournament-registrations.*');
    $adminOpen = request()->routeIs('users.*', 'roles.*', 'permissions.*', 'audits.*', 'biometric.*');

    $canOrganization = auth()->user()?->can('companies.view')
        || auth()->user()?->can('seasons.view')
        || auth()->user()?->can('divisions.view')
        || auth()->user()?->can('teams.view')
        || auth()->user()?->can('categories.view');
    $canTournament = auth()->user()?->can('tournaments.view')
        || auth()->user()?->can('tournament-registrations.view');
    $canAdmin = auth()->user()?->can('users.view')
        || auth()->user()?->can('fingerprint-templates.view')
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

                <li class="nav-item {{ request()->routeIs('biometric.*') ? 'active' : '' }}">
                    <a class="nav-link" href="{{ route('biometric.test') }}">
                        <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-fingerprint-scan"></i></span>
                        <span class="nav-link-title">Prueba biometrica</span>
                    </a>
                </li>

                @if ($canOrganization)
                    <li class="nav-item app-menu-section {{ $organizationOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $organizationOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-organization" aria-expanded="{{ $organizationOpen ? 'true' : 'false' }}" aria-controls="menu-organization">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-building-store"></i></span>
                            <span class="nav-link-title">Ligas</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $organizationOpen ? 'show' : '' }}" id="menu-organization">
                            <ul class="nav app-submenu">
                                @can('companies.view')
                                    <li class="nav-item {{ request()->routeIs('companies.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('companies.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-building"></i></span>
                                            <span class="nav-link-title">Ligas deportivas</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('seasons.view')
                                    <li class="nav-item {{ request()->routeIs('seasons.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('seasons.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar"></i></span>
                                            <span class="nav-link-title">Gestiones</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('divisions.view')
                                    <li class="nav-item {{ request()->routeIs('divisions.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('divisions.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-layers-intersect"></i></span>
                                            <span class="nav-link-title">Divisiones</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('teams.view')
                                    <li class="nav-item {{ request()->routeIs('teams.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('teams.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-shield-star"></i></span>
                                            <span class="nav-link-title">Equipos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('categories.view')
                                    <li class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('categories.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-tags"></i></span>
                                            <span class="nav-link-title">Categorias</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canTournament)
                    <li class="nav-item app-menu-section {{ $tournamentOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $tournamentOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-tournament" aria-expanded="{{ $tournamentOpen ? 'true' : 'false' }}" aria-controls="menu-tournament">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-trophy"></i></span>
                            <span class="nav-link-title">Torneo</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $tournamentOpen ? 'show' : '' }}" id="menu-tournament">
                            <ul class="nav app-submenu">
                                @can('tournaments.view')
                                    <li class="nav-item {{ request()->routeIs('tournaments.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('tournaments.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-trophy"></i></span>
                                            <span class="nav-link-title">Torneos</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('tournament-registrations.view')
                                    <li class="nav-item {{ request()->routeIs('tournament-registrations.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('tournament-registrations.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-clipboard-list"></i></span>
                                            <span class="nav-link-title">Inscripciones</span>
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

                                @can('fingerprint-templates.view')
                                    <li class="nav-item {{ request()->routeIs('fingerprint-templates.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('fingerprint-templates.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-fingerprint"></i></span>
                                            <span class="nav-link-title">Huellas</span>
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
