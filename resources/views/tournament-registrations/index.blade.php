@extends('layouts.admin')

@section('title', 'Inscripciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Inscripciones')
@section('page-subtitle', 'Equipos inscritos por torneo')

@section('content')
    <x-ui.table-card title="Inscripciones por torneo" data-refresh-container>
        <x-slot:actions>
            @can('tournament-registrations.create')
                <a class="btn btn-primary btn-sm" href="{{ route('tournament-registrations.create') }}" data-modal-url="{{ route('tournament-registrations.create') }}" data-modal-title="Nueva inscripcion">Nueva inscripcion</a>
            @endcan
        </x-slot:actions>

        @if ($divisions->isEmpty())
            <div class="text-body-secondary py-4">No hay divisiones activas para filtrar inscripciones.</div>
        @else
            <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('tournament-registrations.index') }}">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label" for="registration-division-filter">Division</label>
                    <select class="form-select" id="registration-division-filter" name="division_id" data-tom-select data-placeholder="Seleccionar division">
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" @selected((int) $activeDivisionId === $division->id)>{{ $division->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary" type="submit">Filtrar</button>
                </div>
            </form>
        @endif

        @if ($divisions->isNotEmpty() && $tournaments->isEmpty())
            <div class="text-body-secondary py-4">No hay torneos activos en la division seleccionada.</div>
        @elseif ($tournaments->isNotEmpty())
            <ul class="nav nav-tabs mb-3" role="tablist">
                @foreach ($tournaments as $tournament)
                    @php
                        $tabId = 'tournament-registration-tab-'.$tournament->id;
                        $registrations = $registrationsByTournament->get($tournament->id, collect());
                    @endphp
                    <li class="nav-item" role="presentation">
                        <button class="nav-link @if ($loop->first) active @endif" id="{{ $tabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}" type="button" role="tab" aria-controls="{{ $tabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            {{ $tournament->name }}
                            <span class="badge text-bg-secondary ms-1">{{ $registrations->count() }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content">
                @foreach ($tournaments as $tournament)
                    @php
                        $tabId = 'tournament-registration-tab-'.$tournament->id;
                        $registrations = $registrationsByTournament->get($tournament->id, collect());
                    @endphp
                    <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $tabId }}" role="tabpanel" aria-labelledby="{{ $tabId }}-tab" tabindex="0">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <div>
                                <div class="fw-semibold">{{ $tournament->name }}</div>
                                <div class="text-body-secondary small">{{ $tournament->season?->name ?? '-' }} · {{ $tournament->division?->name ?? '-' }} · {{ $tournament->category?->name ?? '-' }}</div>
                            </div>
                        </div>
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Equipo</th>
                                    <th>Estado</th>
                                    <th>Notas</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($registrations as $registration)
                                    <tr>
                                        <td class="fw-semibold">{{ $registration->team?->name ?? '-' }}</td>
                                        <td><span class="badge text-bg-{{ registration_status_tone($registration->status) }}">{{ registration_status_label($registration->status) }}</span></td>
                                        <td>{{ $registration->notes ?: '-' }}</td>
                                        <td class="text-end">
                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('tournament-registrations.show', $registration) }}" data-modal-url="{{ route('tournament-registrations.show', $registration) }}" data-modal-title="Detalle de inscripcion">Ver</a>
                                            @can('tournament-registrations.update')
                                                <a class="btn btn-outline-primary btn-sm" href="{{ route('tournament-registrations.edit', $registration) }}" data-modal-url="{{ route('tournament-registrations.edit', $registration) }}" data-modal-title="Editar inscripcion">Editar</a>
                                            @endcan
                                            @can('tournament-registrations.delete')
                                                <form class="d-inline" method="POST" action="{{ route('tournament-registrations.destroy', $registration) }}" data-confirm-delete="Eliminar inscripcion?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <x-ui.empty-row colspan="4" message="No hay equipos inscritos en este torneo." />
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.table-card>
@endsection
