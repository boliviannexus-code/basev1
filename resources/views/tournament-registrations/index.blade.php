@extends('layouts.admin')

@section('title', 'Inscripciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Inscripciones')
@section('page-subtitle', 'Equipos inscritos por torneo')

@section('content')
    @php
        $divisionPalette = [
            ['hue' => 210, 'sat' => 78],
            ['hue' => 156, 'sat' => 64],
            ['hue' => 28, 'sat' => 84],
            ['hue' => 268, 'sat' => 62],
            ['hue' => 346, 'sat' => 72],
            ['hue' => 188, 'sat' => 70],
        ];
    @endphp

    <x-ui.table-card title="Inscripciones por torneo" data-refresh-container>
        <x-slot:actions>
            @can('tournament-registrations.create')
                <a class="btn btn-primary btn-sm" href="{{ route('tournament-registrations.create') }}" data-modal-url="{{ route('tournament-registrations.create') }}" data-modal-title="Nueva inscripcion">Nueva inscripcion</a>
            @endcan
        </x-slot:actions>

        @if ($divisions->isEmpty())
            <div class="text-body-secondary py-4">No hay divisiones activas para filtrar inscripciones.</div>
        @else
            <ul class="nav nav-tabs registration-division-tabs mb-3" role="tablist" aria-label="Divisiones">
                @foreach ($divisions as $division)
                    @php
                        $divisionTabId = 'registration-division-tab-'.$division->id;
                        $divisionTone = $divisionPalette[$loop->index % count($divisionPalette)];
                        $isActiveDivision = (int) $activeDivisionId === $division->id;
                    @endphp
                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link registration-color-tab @if ($isActiveDivision) active @endif"
                            id="{{ $divisionTabId }}-tab"
                            data-bs-toggle="tab"
                            data-bs-target="#{{ $divisionTabId }}"
                            type="button"
                            role="tab"
                            aria-controls="{{ $divisionTabId }}"
                            aria-selected="{{ $isActiveDivision ? 'true' : 'false' }}"
                            style="--registration-hue: {{ $divisionTone['hue'] }}; --registration-sat: {{ $divisionTone['sat'] }}%;"
                        >
                            <span class="registration-tab-title">{{ $division->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <div class="tab-content registration-division-content">
                @foreach ($divisions as $division)
                    @php
                        $divisionTabId = 'registration-division-tab-'.$division->id;
                        $divisionTournaments = $tournamentsByDivision->get($division->id, collect())->values();
                        $divisionTone = $divisionPalette[$loop->index % count($divisionPalette)];
                        $isActiveDivision = (int) $activeDivisionId === $division->id;
                    @endphp
                    <div
                        class="tab-pane fade @if ($isActiveDivision) show active @endif"
                        id="{{ $divisionTabId }}"
                        role="tabpanel"
                        aria-labelledby="{{ $divisionTabId }}-tab"
                        tabindex="0"
                        style="--registration-hue: {{ $divisionTone['hue'] }}; --registration-sat: {{ $divisionTone['sat'] }}%;"
                    >
                        @if ($divisionTournaments->isEmpty())
                            <div class="text-body-secondary py-4">No hay torneos activos en esta division.</div>
                        @else
                            <ul class="nav nav-tabs registration-tournament-tabs mb-3" role="tablist" aria-label="Torneos de {{ $division->name }}">
                                @foreach ($divisionTournaments as $tournament)
                                    @php
                                        $tabId = 'tournament-registration-tab-'.$tournament->id;
                                        $registrations = $registrationsByTournament->get($tournament->id, collect());
                                        $tournamentHue = ($divisionTone['hue'] + ($loop->index * 14)) % 360;
                                    @endphp
                                    <li class="nav-item" role="presentation">
                                        <button
                                            class="nav-link registration-color-tab registration-tournament-tab @if ($loop->first) active @endif"
                                            id="{{ $tabId }}-tab"
                                            data-bs-toggle="tab"
                                            data-bs-target="#{{ $tabId }}"
                                            type="button"
                                            role="tab"
                                            aria-controls="{{ $tabId }}"
                                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                                            style="--registration-hue: {{ $tournamentHue }}; --registration-sat: {{ max(52, $divisionTone['sat'] - 8) }}%;"
                                        >
                                            <span class="registration-tab-title">{{ $tournament->name }}</span>
                                            <span class="badge registration-tab-badge">{{ $registrations->count() }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>

                            <div class="tab-content">
                                @foreach ($divisionTournaments as $tournament)
                                    @php
                                        $tabId = 'tournament-registration-tab-'.$tournament->id;
                                        $registrations = $registrationsByTournament->get($tournament->id, collect());
                                    @endphp
                                    <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $tabId }}" role="tabpanel" aria-labelledby="{{ $tabId }}-tab" tabindex="0">
                                        <div class="registration-tournament-heading mb-3">
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
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.table-card>
@endsection
