@extends('layouts.admin')

@section('title', 'Dashboard | Base Admin')
@section('page-title', 'Dashboard')
@section('page-subtitle', $dashboardCompany ? 'Panel base de '.$dashboardCompany->name : 'Panel base administrativo')

@section('content')
    <div class="dashboard-hero mb-3">
        <div class="dashboard-company">
            @if ($dashboardCompany?->logo_url)
                <img class="dashboard-company-logo" src="{{ $dashboardCompany->logo_url }}" alt="{{ $dashboardCompany->name }}">
            @else
                <span class="dashboard-company-mark">{{ str($dashboardCompany?->name ?? config('app.name', 'BA'))->substr(0, 2)->upper() }}</span>
            @endif
            <div>
                <div class="text-body-secondary small">Base administrativa</div>
                <h2 class="mb-1">{{ $dashboardCompany?->name ?? config('app.name', 'Base Admin') }}</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge text-bg-primary">Laravel</span>
                    <span class="badge text-bg-info">Tabler</span>
                    <span class="badge text-bg-success">Spatie</span>
                    <span class="text-body-secondary small">{{ now()->format('Y-m-d H:i') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Usuarios" :value="$totalUsers" icon="ti ti-users" tone="primary" />
            <div class="dashboard-stat-note">{{ $activeUsers }} usuarios activos</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Roles" :value="$totalRoles" icon="ti ti-lock-access" tone="success" />
            <div class="dashboard-stat-note">Gestionados con Spatie</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Permisos" :value="$totalPermissions" icon="ti ti-shield-check" tone="warning" />
            <div class="dashboard-stat-note">Control de acceso listo</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Ligas deportivas" :value="$totalCompanies" icon="ti ti-building" tone="info" />
            <div class="dashboard-stat-note">Contexto organizacional</div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <x-ui.table-card title="Actividad reciente">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Evento</th>
                            <th>Modelo</th>
                            <th>Usuario</th>
                            <th class="text-end">Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentAudits as $audit)
                            <tr>
                                <td><span class="badge text-bg-secondary">{{ str($audit->event)->headline() }}</span></td>
                                <td>{{ class_basename($audit->auditable_type) }}</td>
                                <td>{{ $audit->user?->name ?? 'Sistema' }}</td>
                                <td class="text-end">{{ $audit->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-3" colspan="4">Sin auditorias recientes.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

        <div class="col-lg-5">
            <x-ui.card title="Base lista">
                <div class="card-body">
                    <div class="dashboard-alert-row">
                        <span class="avatar avatar-sm bg-primary-lt text-primary"><i class="ti ti-login"></i></span>
                        <div>
                            <div class="fw-semibold">Autenticacion</div>
                            <div class="text-body-secondary small">Login web y API con Sanctum disponibles.</div>
                        </div>
                    </div>
                    <div class="dashboard-alert-row">
                        <span class="avatar avatar-sm bg-success-lt text-success"><i class="ti ti-shield-lock"></i></span>
                        <div>
                            <div class="fw-semibold">Roles y permisos</div>
                            <div class="text-body-secondary small">Usuarios, roles y permisos se mantienen como nucleo.</div>
                        </div>
                    </div>
                    <div class="dashboard-alert-row">
                        <span class="avatar avatar-sm bg-warning-lt text-warning"><i class="ti ti-history"></i></span>
                        <div>
                            <div class="fw-semibold">Auditoria</div>
                            <div class="text-body-secondary small">Cambios relevantes quedan registrados para consulta.</div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
