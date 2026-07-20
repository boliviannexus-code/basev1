@extends('layouts.admin')

@section('title', 'Asistencia | '.config('app.name', 'Base Admin'))
@section('page-title', 'Control de asistencia')
@section('page-subtitle', $meeting->title)

@section('content')
    @php
        $total = $meeting->attendances->count();
        $present = $meeting->attendances->where('present', true)->count();
        $permission = $meeting->attendances->where('permission_requested', true)->count();
        $absent = max(0, $total - $present - $permission);
        $finished = $meeting->isFinished();
    @endphp

    <div class="row g-3 mb-3" data-meeting-summary>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">Fecha</div>
                <div class="h3 mb-0">{{ $meeting->meeting_date?->format('d/m/Y') ?? '-' }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">Equipos</div>
                <div class="h3 mb-0" data-meeting-total-count>{{ $total }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">Presentes</div>
                <div class="h3 mb-0 text-success" data-meeting-present-count>{{ $present }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">Permisos</div>
                <div class="h3 mb-0 text-warning" data-meeting-permission-count>{{ $permission }}</div>
            </div></div>
        </div>
        <div class="col-md-2">
            <div class="card"><div class="card-body py-3">
                <div class="text-body-secondary small text-uppercase fw-semibold">Ausentes</div>
                <div class="h3 mb-0 text-danger" data-meeting-absent-count>{{ $absent }}</div>
            </div></div>
        </div>
    </div>

    <x-ui.table-card title="Equipos convocados">
        <x-slot:actions>
            <div class="d-flex flex-column flex-md-row gap-2">
                <div class="input-icon">
                    <span class="input-icon-addon"><i class="ti ti-search"></i></span>
                    <input class="form-control" type="search" placeholder="Buscar equipo" aria-label="Buscar equipo" data-meeting-attendance-search>
                </div>
                <a class="btn btn-outline-secondary" href="{{ route('meetings.index') }}">
                    <i class="ti ti-arrow-left me-1"></i>
                    Volver
                </a>
                @if ($finished)
                    <a class="btn btn-success" href="{{ route('meetings.pdf', $meeting) }}" target="_blank" rel="noopener">
                        <i class="ti ti-printer me-1"></i>
                        Imprimir
                    </a>
                @else
                    @can('meetings.update')
                        <form method="POST" action="{{ route('meetings.finish', $meeting) }}" data-confirm-meeting-finish>
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-success" type="submit">
                                <i class="ti ti-flag-check me-1"></i>
                                Finalizar
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </x-slot:actions>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th style="width: 13rem;">Estado</th>
                        <th class="text-end" style="width: 25rem;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($meeting->attendances as $attendance)
                        <tr data-meeting-attendance-row data-team-name="{{ str($attendance->team?->name ?? '')->lower()->toString() }}">
                            <td>
                                <div class="fw-semibold">{{ $attendance->team?->name ?? '-' }}</div>
                                <div class="text-body-secondary small">
                                    {{ $attendance->represented_categories ?: ($attendance->tournamentRegistration?->category?->name ?? 'Sin categoria') }}
                                </div>
                                <div class="text-body-secondary small">{{ $attendance->tournamentRegistration?->tournament?->name ?? '-' }}</div>
                            </td>
                            <td data-meeting-attendance-status>
                                @if ($attendance->present)
                                    <span class="badge text-bg-success">Presente</span>
                                    <div class="text-body-secondary small">{{ $attendance->attended_at?->format('H:i') ?? '-' }}</div>
                                @elseif ($attendance->permission_requested)
                                    <span class="badge text-bg-warning">Permiso</span>
                                    <div class="text-body-secondary small">{{ $attendance->permission_requested_at?->format('H:i') ?? '-' }}</div>
                                @else
                                    <span class="badge text-bg-secondary">Ausente</span>
                                @endif
                            </td>
                            <td class="text-end">
                                @if (! $finished)
                                    @can('meetings.update')
                                        <div class="d-inline-flex justify-content-end align-items-center gap-2 flex-nowrap w-100">
                                            <label class="form-check form-switch d-inline-flex align-items-center gap-2 m-0">
                                                <input
                                                    class="form-check-input"
                                                    type="checkbox"
                                                    role="switch"
                                                    data-meeting-attendance-toggle
                                                    data-url="{{ route('meetings.attendances.update', [$meeting, $attendance]) }}"
                                                    @checked($attendance->present)
                                                    @disabled($finished || ! auth()->user()?->can('meetings.update'))
                                                >
                                                <span class="fw-semibold {{ $attendance->present ? 'text-success' : 'text-body-secondary' }}" data-meeting-attendance-label>
                                                    {{ $attendance->present ? 'Presente' : 'Marcar asistencia' }}
                                                </span>
                                            </label>
                                            <form class="d-inline-flex align-items-center gap-1 flex-nowrap" method="POST" action="{{ route('meetings.attendances.permission', [$meeting, $attendance]) }}" data-meeting-permission-form>
                                                @csrf
                                                @method('PATCH')
                                                <input class="form-control form-control-sm" style="width: 9rem;" name="permission_reason" placeholder="Motivo permiso" @disabled($attendance->present)>
                                                <button class="btn btn-outline-warning btn-sm" type="submit" @disabled($attendance->present || $attendance->permission_requested)>
                                                    Permiso
                                                </button>
                                            </form>
                                        </div>
                                    @endcan
                                @else
                                    <span class="text-body-secondary small">{{ $attendance->permission_reason ?: '-' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="3" message="No hay equipos activos para registrar asistencia." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.table-card>
@endsection
