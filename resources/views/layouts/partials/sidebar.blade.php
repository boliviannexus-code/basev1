@php
    $leagueOpen = request()->routeIs('companies.*', 'website-page.*', 'courts.*', 'seasons.*', 'divisions.*', 'categories.*', 'teams.*', 'players.*', 'player-imports.*');
    $tournamentOpen = request()->routeIs('tournaments.*', 'tournament-registrations.*', 'fixtures.*', 'standings.*', 'player-habilitations.*', 'player-transfers.*');
    $reportsOpen = request()->routeIs('sports-reports.*');
    $matchdayOpen = request()->routeIs('matchdays.*', 'match-reports.*');
    $penaltiesOpen = request()->routeIs('red-cards.*', 'red-card-articles.*', 'punishments.*');
    $meetingsOpen = request()->routeIs('accreditations.*', 'meetings.*');
    $settingsOpen = request()->routeIs('league-settings.*');
    $adminOpen = request()->routeIs('users.*', 'roles.*', 'permissions.*', 'audits.*', 'biometric.*');

    $canLeague = auth()->user()?->can('companies.view')
        || auth()->user()?->can('companies.update')
        || auth()->user()?->can('courts.view')
        || auth()->user()?->can('seasons.view')
        || auth()->user()?->can('divisions.view')
        || auth()->user()?->can('categories.view')
        || auth()->user()?->can('teams.view')
        || auth()->user()?->can('players.view')
        || auth()->user()?->can('player-imports.view');
    $canTournament = auth()->user()?->can('tournaments.view')
        || auth()->user()?->can('tournament-registrations.view')
        || auth()->user()?->can('fixtures.view')
        || auth()->user()?->can('standings.view')
        || auth()->user()?->can('player-habilitations.view')
        || auth()->user()?->can('player-transfers.view');
    $canReports = auth()->user()?->can('sports-reports.view');
    $canMatchday = auth()->user()?->can('matchdays.view')
        || auth()->user()?->can('match-reports.view');
    $canPenalties = auth()->user()?->can('red-cards.view')
        || auth()->user()?->can('red-card-articles.view')
        || auth()->user()?->can('punishments.view');
    $canMeetings = auth()->user()?->can('accreditations.view')
        || auth()->user()?->can('meetings.view');
    $canSettings = auth()->user()?->can('league-settings.view');
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

                @if ($canLeague)
                    <li class="nav-item app-menu-section {{ $leagueOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $leagueOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-league" aria-expanded="{{ $leagueOpen ? 'true' : 'false' }}" aria-controls="menu-league">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-building-store"></i></span>
                            <span class="nav-link-title">Liga</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $leagueOpen ? 'show' : '' }}" id="menu-league">
                            <ul class="nav app-submenu">
                                @can('companies.view')
                                    <li class="nav-item {{ request()->routeIs('companies.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('companies.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-building"></i></span>
                                            <span class="nav-link-title">Ligas deportivas</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('companies.update')
                                    <li class="nav-item {{ request()->routeIs('website-page.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('website-page.edit') }}">
                                            <span class="nav-link-icon"><i class="ti ti-world-www"></i></span>
                                            <span class="nav-link-title">Pagina web</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('courts.view')
                                    <li class="nav-item {{ request()->routeIs('courts.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('courts.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-map-pin"></i></span>
                                            <span class="nav-link-title">Canchas</span>
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
                                @can('categories.view')
                                    <li class="nav-item {{ request()->routeIs('categories.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('categories.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-tags"></i></span>
                                            <span class="nav-link-title">Categorias</span>
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
                                @can('players.view')
                                    <li class="nav-item {{ request()->routeIs('players.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('players.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-user-star"></i></span>
                                            <span class="nav-link-title">Jugadores</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('player-imports.view')
                                    <li class="nav-item {{ request()->routeIs('player-imports.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('player-imports.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-file-spreadsheet"></i></span>
                                            <span class="nav-link-title">Importar jugadores</span>
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
                                @can('fixtures.view')
                                    <li class="nav-item {{ request()->routeIs('fixtures.index', 'fixtures.categories', 'fixtures.series', 'fixtures.configure', 'fixtures.report') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('fixtures.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-tournament"></i></span>
                                            <span class="nav-link-title">Fixture</span>
                                        </a>
                                    </li>
                                    <li class="nav-item {{ request()->routeIs('fixtures.patterns') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('fixtures.patterns') }}">
                                            <span class="nav-link-icon"><i class="ti ti-printer"></i></span>
                                            <span class="nav-link-title">Plantillas fixture</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('standings.view')
                                    <li class="nav-item {{ request()->routeIs('standings.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('standings.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-list-numbers"></i></span>
                                            <span class="nav-link-title">Tabla de posiciones</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('player-habilitations.view')
                                    <li class="nav-item {{ request()->routeIs('player-habilitations.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('player-habilitations.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-user-check"></i></span>
                                            <span class="nav-link-title">Habilitaciones</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('player-transfers.view')
                                    <li class="nav-item {{ request()->routeIs('player-transfers.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('player-transfers.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-switch-horizontal"></i></span>
                                            <span class="nav-link-title">Pases</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canReports)
                    <li class="nav-item app-menu-section {{ $reportsOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $reportsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-reports" aria-expanded="{{ $reportsOpen ? 'true' : 'false' }}" aria-controls="menu-reports">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-report"></i></span>
                            <span class="nav-link-title">Reportes</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $reportsOpen ? 'show' : '' }}" id="menu-reports">
                            <ul class="nav app-submenu">
                                <li class="nav-item {{ request()->routeIs('sports-reports.registered-teams*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.registered-teams') }}">
                                        <span class="nav-link-icon"><i class="ti ti-report"></i></span>
                                        <span class="nav-link-title">Equipos inscritos</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.enabled-players*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.enabled-players') }}">
                                        <span class="nav-link-icon"><i class="ti ti-report-analytics"></i></span>
                                        <span class="nav-link-title">Jugadores habilitados</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.transfers*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.transfers') }}">
                                        <span class="nav-link-icon"><i class="ti ti-report-money"></i></span>
                                        <span class="nav-link-title">Pases</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.player-kardex*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.player-kardex') }}">
                                        <span class="nav-link-icon"><i class="ti ti-id"></i></span>
                                        <span class="nav-link-title">Kardex jugador</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.finalized-matchdays*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.finalized-matchdays') }}">
                                        <span class="nav-link-icon"><i class="ti ti-calendar-check"></i></span>
                                        <span class="nav-link-title">Jornadas finalizadas</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.yellow-cards*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.yellow-cards') }}">
                                        <span class="nav-link-icon"><i class="ti ti-cards"></i></span>
                                        <span class="nav-link-title">Tarjetas amarillas</span>
                                    </a>
                                </li>
                                <li class="nav-item {{ request()->routeIs('sports-reports.red-cards*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('sports-reports.red-cards') }}">
                                        <span class="nav-link-icon"><i class="ti ti-cardboards"></i></span>
                                        <span class="nav-link-title">Tarjetas rojas</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canMatchday)
                    <li class="nav-item app-menu-section {{ $matchdayOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $matchdayOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-matchday" aria-expanded="{{ $matchdayOpen ? 'true' : 'false' }}" aria-controls="menu-matchday">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-calendar-event"></i></span>
                            <span class="nav-link-title">Jornada</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $matchdayOpen ? 'show' : '' }}" id="menu-matchday">
                            <ul class="nav app-submenu">
                                @can('matchdays.view')
                                    <li class="nav-item {{ request()->routeIs('matchdays.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('matchdays.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-calendar-event"></i></span>
                                            <span class="nav-link-title">Jornadas</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('match-reports.view')
                                    <li class="nav-item {{ request()->routeIs('match-reports.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('match-reports.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-clipboard-check"></i></span>
                                            <span class="nav-link-title">Registro de partidos</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canPenalties)
                    <li class="nav-item app-menu-section {{ $penaltiesOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $penaltiesOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-penalties" aria-expanded="{{ $penaltiesOpen ? 'true' : 'false' }}" aria-controls="menu-penalties">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-gavel"></i></span>
                            <span class="nav-link-title">Comite de Penas</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $penaltiesOpen ? 'show' : '' }}" id="menu-penalties">
                            <ul class="nav app-submenu">
                                @can('red-cards.view')
                                    <li class="nav-item {{ request()->routeIs('red-cards.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('red-cards.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-cards"></i></span>
                                            <span class="nav-link-title">Tarjetas rojas</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('red-card-articles.view')
                                    <li class="nav-item {{ request()->routeIs('red-card-articles.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('red-card-articles.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-section"></i></span>
                                            <span class="nav-link-title">Articulos sancion</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('punishments.view')
                                    <li class="nav-item {{ request()->routeIs('punishments.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('punishments.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-gavel"></i></span>
                                            <span class="nav-link-title">Castigos</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canMeetings)
                    <li class="nav-item app-menu-section {{ $meetingsOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $meetingsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-meetings" aria-expanded="{{ $meetingsOpen ? 'true' : 'false' }}" aria-controls="menu-meetings">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-users-group"></i></span>
                            <span class="nav-link-title">Reuniones</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $meetingsOpen ? 'show' : '' }}" id="menu-meetings">
                            <ul class="nav app-submenu">
                                @can('accreditations.view')
                                    <li class="nav-item {{ request()->routeIs('accreditations.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('accreditations.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-id-badge-2"></i></span>
                                            <span class="nav-link-title">Acreditaciones</span>
                                        </a>
                                    </li>
                                @endcan
                                @can('meetings.view')
                                    <li class="nav-item {{ request()->routeIs('meetings.*') ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ route('meetings.index') }}">
                                            <span class="nav-link-icon"><i class="ti ti-users-group"></i></span>
                                            <span class="nav-link-title">Asistencia reuniones</span>
                                        </a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endif

                @if ($canSettings)
                    <li class="nav-item app-menu-section {{ $settingsOpen ? 'active' : '' }}">
                        <button class="nav-link app-menu-toggle {{ $settingsOpen ? '' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#menu-settings" aria-expanded="{{ $settingsOpen ? 'true' : 'false' }}" aria-controls="menu-settings">
                            <span class="nav-link-icon d-md-none d-lg-inline-block"><i class="ti ti-adjustments-horizontal"></i></span>
                            <span class="nav-link-title">Configuraciones</span>
                            <span class="menu-chevron"><i class="ti ti-chevron-down"></i></span>
                        </button>
                        <div class="collapse {{ $settingsOpen ? 'show' : '' }}" id="menu-settings">
                            <ul class="nav app-submenu">
                                <li class="nav-item {{ request()->routeIs('league-settings.*') ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ route('league-settings.index') }}">
                                        <span class="nav-link-icon"><i class="ti ti-cash"></i></span>
                                        <span class="nav-link-title">Parametros de liga</span>
                                    </a>
                                </li>
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
