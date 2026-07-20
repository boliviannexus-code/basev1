@extends('layouts.admin')

@section('title', 'Asistencia a reuniones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Asistencia a reuniones')
@section('page-subtitle', 'Inicio, control y reporte de asistencia por equipos')

@section('content')
    @can('meetings.create')
        <div class="card mb-3">
            <form class="card-body" method="POST" action="{{ route('meetings.store') }}">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label" for="meeting-title">Nombre de la reunion</label>
                        <input class="form-control @error('title') is-invalid @enderror" id="meeting-title" name="title" value="{{ old('title', 'Reunion de delegados') }}" required>
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-3">
                        <label class="form-label" for="meeting-date">Fecha</label>
                        <input class="form-control @error('meeting_date') is-invalid @enderror" id="meeting-date" name="meeting_date" type="date" value="{{ old('meeting_date', now()->toDateString()) }}" required>
                        @error('meeting_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label" for="meeting-notes">Detalle</label>
                        <input class="form-control @error('notes') is-invalid @enderror" id="meeting-notes" name="notes" value="{{ old('notes') }}" placeholder="Opcional">
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-2">
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ti ti-player-play me-1"></i>
                            Iniciar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    @endcan

    <x-ui.table-card title="Reuniones registradas">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>Reunion</th>
                    <th>Fecha</th>
                    <th>Asistencia</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($meetings as $meeting)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $meeting->title }}</div>
                            <div class="text-body-secondary small">{{ $meeting->company?->name ?? '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $meeting->meeting_date?->format('d/m/Y') ?? '-' }}</div>
                            <div class="text-body-secondary small">{{ $meeting->started_at?->format('H:i') ?? '-' }}</div>
                        </td>
                        <td>
                            <div class="fw-semibold">{{ $meeting->present_count }} / {{ $meeting->attendances_count }}</div>
                            <div class="text-body-secondary small">{{ $meeting->permission_count }} permisos · {{ max(0, $meeting->attendances_count - $meeting->present_count - $meeting->permission_count) }} ausentes</div>
                        </td>
                        <td>
                            <span class="badge text-bg-{{ $meeting->status === \App\Models\Meeting::STATUS_FINISHED ? 'success' : 'warning' }}">
                                {{ $meeting->status === \App\Models\Meeting::STATUS_FINISHED ? 'Finalizada' : 'Abierta' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('meetings.show', $meeting) }}">
                                <i class="ti ti-checklist me-1"></i>
                                Asistencia
                            </a>
                            @if ($meeting->status === \App\Models\Meeting::STATUS_FINISHED)
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('meetings.pdf', $meeting) }}" target="_blank" rel="noopener">
                                    <i class="ti ti-printer me-1"></i>
                                    Imprimir
                                </a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay reuniones registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $meetings->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
