@extends('layouts.admin')

@section('title', 'Dashboard | '.config('app.name', 'Base Admin'))
@section('page-title', 'Dashboard')
@section('page-subtitle', $dashboardCompany ? 'Ocupabilidad de '.$dashboardCompany->name : 'Ocupabilidad por empresa')

@section('content')
    @php
        $statusTones = [
            'pending_payment' => 'warning',
            'payment_under_review' => 'info',
            'confirmed' => 'success',
            'checked_in' => 'primary',
            'cancelled' => 'secondary',
            'expired' => 'secondary',
        ];
        $modeLabel = fn (?string $mode): string => $mode === 'compartido' ? 'Compartido' : ($mode === 'privado' ? 'Privado' : 'Sin modalidad');
    @endphp

    <div class="dashboard-hero mb-3">
        <div class="dashboard-company">
            @if ($dashboardCompany?->logo_url)
                <img class="dashboard-company-logo" src="{{ $dashboardCompany->logo_url }}" alt="{{ $dashboardCompany->name }}">
            @else
                <span class="dashboard-company-mark">{{ str($dashboardCompany?->name ?? 'Empresas')->substr(0, 2)->upper() }}</span>
            @endif
            <div>
                <div class="text-body-secondary small">{{ $dashboardCompany ? 'Contexto de empresa' : 'Contexto global' }}</div>
                <h2 class="mb-1">{{ $dashboardCompany?->name ?? 'Todas las empresas' }}</h2>
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge text-bg-primary">{{ $today->format('Y-m-d') }}</span>
                    <span class="badge text-bg-info">Sin datos financieros</span>
                    <span class="text-body-secondary small">Desayuno calculado con ocupacion del {{ $yesterday->format('Y-m-d') }}</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Habitaciones compartidas ocupadas" :value="$occupancy['shared_rooms']" icon="ti ti-door" tone="primary" />
            <div class="dashboard-stat-note">{{ $occupancy['shared_guests'] }} huespedes en compartidos</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Espacios privados ocupados" :value="$occupancy['private_spaces']" icon="ti ti-home" tone="success" />
            <div class="dashboard-stat-note">{{ $occupancy['private_guests'] }} huespedes en privados</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Desayunos para hoy" :value="$breakfast['total_people']" icon="ti ti-coffee" tone="warning" />
            <div class="dashboard-stat-note">{{ $breakfast['shared_people'] }} compartido · {{ $breakfast['private_people'] }} privado</div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-card label="Llegadas hoy" :value="$reservations['arrivals_today']" icon="ti ti-calendar-event" tone="info" />
            <div class="dashboard-stat-note">{{ $reservations['arrivals_tomorrow'] }} llegadas manana</div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-xl-7">
            <x-ui.table-card title="Ocupabilidad por espacio">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Espacio</th>
                            <th>Modalidad</th>
                            <th class="text-end">Unidades ocupadas</th>
                            <th class="text-end">Huespedes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($occupancy['by_space'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['space']?->title ?: $row['space']?->name ?: 'Espacio' }}</td>
                                <td><span class="badge text-bg-secondary">{{ $modeLabel($row['mode']) }}</span></td>
                                <td class="text-end">{{ $row['occupied_units'] }}</td>
                                <td class="text-end fw-semibold">{{ $row['guests'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4" colspan="4">Sin ocupacion activa para hoy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

        <div class="col-xl-5">
            <x-ui.table-card title="Desayunos por espacio">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Espacio</th>
                            <th>Modalidad</th>
                            <th class="text-end">Personas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($breakfast['by_space'] as $row)
                            <tr>
                                <td class="fw-semibold">{{ $row['space']?->title ?: $row['space']?->name ?: 'Espacio' }}</td>
                                <td><span class="badge text-bg-secondary">{{ $modeLabel($row['mode']) }}</span></td>
                                <td class="text-end fw-semibold">{{ $row['people'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4" colspan="3">Sin desayunos programados para hoy.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>

    <div class="row g-3 mt-1">
        @if ($dashboardCompany)
            @php($companySummary = $dashboardCompanies->first())
            <div class="col-xl-4">
                <x-ui.card title="Informacion de empresa">
                    <div class="card-body">
                        <dl class="reservation-admin-dl mb-0">
                            <div><dt>Empresa</dt><dd>{{ $dashboardCompany->name }}</dd></div>
                            <div><dt>Telefono</dt><dd>{{ $dashboardCompany->phone ?: $dashboardCompany->whatsapp ?: 'Sin telefono' }}</dd></div>
                            <div><dt>Correo</dt><dd>{{ $dashboardCompany->email ?: 'Sin correo' }}</dd></div>
                            <div><dt>Ciudad</dt><dd>{{ $dashboardCompany->city ?: 'Sin ciudad' }}</dd></div>
                            <div><dt>Usuarios</dt><dd>{{ $companySummary?->users_count ?? 0 }}</dd></div>
                            <div><dt>Espacios activos</dt><dd>{{ $companySummary?->active_spaces_count ?? 0 }}</dd></div>
                            <div><dt>Privados / Compartidos</dt><dd>{{ $companySummary?->private_spaces_count ?? 0 }} / {{ $companySummary?->shared_spaces_count ?? 0 }}</dd></div>
                        </dl>
                    </div>
                </x-ui.card>
            </div>
        @endif

        <div class="col-xl-4">
            <x-ui.table-card title="Reservas por estado">
                <table class="table table-sm table-hover align-middle mb-0">
                    <tbody>
                        @foreach ($reservations['status_counts'] as $row)
                            <tr>
                                <td><span class="badge text-bg-{{ $statusTones[$row['status']] ?? 'secondary' }}">{{ $row['label'] }}</span></td>
                                <td class="text-end fw-semibold">{{ $row['total'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

        <div class="col-xl-8">
            <x-ui.table-card title="Proximas reservas">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            @if (! $dashboardCompany)<th>Empresa</th>@endif
                            <th>Codigo</th>
                            <th>Huesped</th>
                            <th>Ingreso</th>
                            <th>Salida</th>
                            <th class="text-end">Personas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($reservations['upcoming'] as $row)
                            <tr>
                                @if (! $dashboardCompany)<td>{{ $row['company'] ?: '-' }}</td>@endif
                                <td>
                                    <div class="fw-semibold">{{ $row['code'] }}</div>
                                    <div class="text-body-secondary small">{{ $row['type'] }} · {{ str($row['status'])->replace('_', ' ') }}</div>
                                </td>
                                <td>{{ $row['guest'] ?: 'Sin huesped' }}</td>
                                <td>{{ $row['check_in']?->format('Y-m-d') }}</td>
                                <td>{{ $row['check_out']?->format('Y-m-d') }}</td>
                                <td class="text-end fw-semibold">{{ $row['guests'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="text-center text-body-secondary py-4" colspan="{{ $dashboardCompany ? 5 : 6 }}">Sin reservas proximas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>
    </div>

    @if (! $dashboardCompany)
        <x-ui.table-card title="Empresas" class="mt-3">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th class="text-end">Usuarios</th>
                        <th class="text-end">Espacios activos</th>
                        <th class="text-end">Privados</th>
                        <th class="text-end">Compartidos</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($dashboardCompanies as $company)
                        <tr>
                            <td class="fw-semibold">{{ $company->name }}</td>
                            <td class="text-end">{{ $company->users_count }}</td>
                            <td class="text-end">{{ $company->active_spaces_count }}</td>
                            <td class="text-end">{{ $company->private_spaces_count }}</td>
                            <td class="text-end">{{ $company->shared_spaces_count }}</td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-body-secondary py-4" colspan="5">Sin empresas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>
    @endif
@endsection
