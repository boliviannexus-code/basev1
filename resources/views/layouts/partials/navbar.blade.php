@php
    $navbarCompany = \App\Support\CompanyContext::activeCompany(auth()->user());
@endphp

<header class="navbar navbar-expand-md d-print-none app-navbar">
    <div class="container-xl">
        <button class="btn btn-icon d-inline-flex me-3" type="button" data-sidebar-toggle aria-label="Replegar menu" title="Replegar menu" aria-controls="adminSidebar" aria-expanded="false">
            <i class="ti ti-layout-sidebar-left-collapse"></i>
        </button>

        <div class="navbar-brand d-none-navbar-horizontal pe-0 pe-md-3">
            <div class="d-flex align-items-center gap-3">
                @if ($navbarCompany?->logo_url)
                    <span class="avatar avatar-md" style="background-image: url('{{ $navbarCompany->logo_url }}')"></span>
                @else
                    <span class="avatar avatar-md bg-primary-lt text-primary">
                        <i class="ti ti-building-store fs-2"></i>
                    </span>
                @endif
                <div>
                    <div class="page-title mb-0">@yield('page-title', 'Panel administrativo')</div>
                    @hasSection('page-subtitle')
                        <div class="text-muted small">@yield('page-subtitle')</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="navbar-nav flex-row align-items-center order-md-last ms-auto">
            <div class="nav-item dropdown">
                <button class="nav-link d-flex lh-1 text-reset p-0 border-0 bg-transparent" type="button" data-user-dropdown-toggle aria-expanded="false" aria-label="Abrir menu de usuario">
                    <span class="avatar avatar-sm">{{ str(auth()->user()->name ?? 'U')->substr(0, 1)->upper() }}</span>
                    <div class="d-none d-xl-block ps-2">
                        <div>{{ auth()->user()->name ?? 'Usuario' }}</div>
                        <div class="mt-1 small text-muted">{{ auth()->user()->email ?? '' }}</div>
                    </div>
                    <i class="ti ti-chevron-down ms-2 align-self-center text-muted"></i>
                </button>
                <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                    <span class="dropdown-item-text text-muted small">{{ auth()->user()->email ?? '' }}</span>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="{{ route('my-account.edit') }}">
                        <i class="ti ti-user-cog me-2"></i>Mi cuenta
                    </a>
                    <div class="dropdown-divider"></div>
                    <form class="m-0" method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="dropdown-item text-danger" type="submit">
                            <i class="ti ti-logout me-2"></i>Cerrar sesion
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>
