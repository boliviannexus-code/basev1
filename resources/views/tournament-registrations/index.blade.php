@extends('layouts.admin')

@section('title', 'Inscripciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Inscripciones')
@section('page-subtitle', 'Equipos inscritos por torneo')

@section('content')
    <div data-refresh-container>
        <x-ui.table-card title="Equipos disponibles para inscripcion">
            <table
                class="table table-hover align-middle"
                data-datatable
                data-order='[[0,"asc"]]'
                data-page-length="10"
            >
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Inscripciones actuales</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td class="fw-semibold">{{ $team->name }}</td>
                            <td>
                                @forelse ($team->registrations as $registration)
                                    <span class="badge text-bg-light border text-body me-1 mb-1">
                                        {{ $registration->tournament?->name ?? '-' }}
                                        @if ($registration->category)
                                            · {{ $registration->category->name }}
                                        @endif
                                        · {{ $registration->seriesLabel() }}
                                    </span>
                                @empty
                                    <span class="text-body-secondary">Sin inscripciones activas</span>
                                @endforelse
                            </td>
                            <td class="text-end">
                                @can('tournament-registrations.create')
                                    <a class="btn btn-primary btn-sm" href="{{ route('tournament-registrations.create', ['team_id' => $team->id]) }}" data-modal-url="{{ route('tournament-registrations.create', ['team_id' => $team->id]) }}" data-modal-title="Inscribir equipo">Inscribir</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="3" message="No hay equipos registrados para inscribir." />
                    @endforelse
                </tbody>
            </table>
        </x-ui.table-card>

        <x-ui.table-card title="Inscripciones por torneo">
            @if ($tournaments->isEmpty())
                <div class="text-body-secondary py-4">No hay torneos con inscripciones registradas.</div>
            @else
                <ul class="nav nav-tabs registration-filter-tabs registration-tournament-tabs mb-3" role="tablist" aria-label="Torneos">
                    @foreach ($tournaments as $tournament)
                        @php
                            $tabId = 'tournament-registration-tab-'.$tournament->id;
                            $registrations = $registrationsByTournament->get($tournament->id, collect());
                        @endphp
                        <li class="nav-item" role="presentation">
                            <button class="nav-link registration-filter-tab @if ($loop->first) active @endif" id="{{ $tabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $tabId }}" type="button" role="tab" aria-controls="{{ $tabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                <span class="registration-tab-title">{{ $tournament->name }}</span>
                                <span class="badge registration-tab-badge">{{ $registrations->count() }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>

                <div class="tab-content registration-tournament-content">
                    @foreach ($tournaments as $tournament)
                        @php
                            $tabId = 'tournament-registration-tab-'.$tournament->id;
                            $registrations = $registrationsByTournament->get($tournament->id, collect());
                            $registrationsByCategory = $registrations->groupBy(fn ($registration) => $registration->category_id ?: 'none');
                        @endphp
                        <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $tabId }}" role="tabpanel" aria-labelledby="{{ $tabId }}-tab" tabindex="0">
                            <div class="registration-tournament-heading mb-3">
                                <div>
                                    <div class="fw-semibold">{{ $tournament->name }}</div>
                                    <div class="text-body-secondary small">
                                        {{ $tournament->season?->name ?? '-' }} · {{ $tournament->division?->name ?? '-' }} · {{ $tournament->categories->pluck('name')->implode(', ') ?: '-' }}
                                    </div>
                                </div>
                                <span class="badge text-bg-primary">{{ $registrations->count() }} equipos</span>
                            </div>

                            @if ($registrationsByCategory->count() > 1)
                                <ul class="nav nav-tabs registration-filter-tabs compact mb-3" role="tablist" aria-label="Categorias de {{ $tournament->name }}">
                                    @foreach ($registrationsByCategory as $categoryKey => $categoryRegistrations)
                                        @php
                                            $category = $categoryRegistrations->first()?->category;
                                            $categoryTabId = 'registration-category-tab-'.$tournament->id.'-'.$categoryKey;
                                        @endphp
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link registration-filter-tab compact @if ($loop->first) active @endif" id="{{ $categoryTabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $categoryTabId }}" type="button" role="tab" aria-controls="{{ $categoryTabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                <span class="registration-tab-title">{{ $category?->name ?? 'Sin categoria' }}</span>
                                                <span class="badge registration-tab-badge">{{ $categoryRegistrations->count() }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="tab-content">
                                @foreach ($registrationsByCategory as $categoryKey => $categoryRegistrations)
                                    @php
                                        $categoryTabId = 'registration-category-tab-'.$tournament->id.'-'.$categoryKey;
                                        $registrationsBySeries = $categoryRegistrations->groupBy('series');
                                    @endphp
                                    <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $categoryTabId }}" role="tabpanel" aria-labelledby="{{ $categoryTabId }}-tab" tabindex="0">
                                        @if ($registrationsBySeries->count() > 1)
                                            <ul class="nav nav-tabs registration-filter-tabs compact mb-3" role="tablist" aria-label="Series">
                                                @foreach ($registrationsBySeries as $seriesKey => $seriesRegistrations)
                                                    @php
                                                        $seriesTabId = 'registration-series-tab-'.$tournament->id.'-'.$categoryKey.'-'.$seriesKey;
                                                    @endphp
                                                    <li class="nav-item" role="presentation">
                                                        <button class="nav-link registration-filter-tab compact @if ($loop->first) active @endif" id="{{ $seriesTabId }}-tab" data-bs-toggle="tab" data-bs-target="#{{ $seriesTabId }}" type="button" role="tab" aria-controls="{{ $seriesTabId }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                                            <span class="registration-tab-title">{{ $seriesRegistrations->first()?->seriesLabel() ?? 'Unica' }}</span>
                                                            <span class="badge registration-tab-badge">{{ $seriesRegistrations->count() }}</span>
                                                        </button>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif

                                        <div class="tab-content">
                                            @foreach ($registrationsBySeries as $seriesKey => $seriesRegistrations)
                                                @php
                                                    $seriesTabId = 'registration-series-tab-'.$tournament->id.'-'.$categoryKey.'-'.$seriesKey;
                                                @endphp
                                                <div class="tab-pane fade @if ($loop->first) show active @endif" id="{{ $seriesTabId }}" role="tabpanel" aria-labelledby="{{ $seriesTabId }}-tab" tabindex="0">
                                                    <div class="table-responsive">
                                                        <table class="table table-hover align-middle registration-roster-table">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 96px;">#</th>
                                                                    <th>Equipo</th>
                                                                    <th>Categoria</th>
                                                                    <th>Serie</th>
                                                                    <th>Estado</th>
                                                                    <th>Notas</th>
                                                                    <th class="text-end">Acciones</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($seriesRegistrations as $registration)
                                                                    <tr>
                                                                        <td>
                                                                            @can('tournament-registrations.update')
                                                                                <form method="POST" action="{{ route('tournament-registrations.team-number.update', $registration) }}" data-registration-team-number-form>
                                                                                    @csrf
                                                                                    @method('PATCH')
                                                                                    <label class="visually-hidden" for="registration-team-number-{{ $registration->id }}">Numero de equipo</label>
                                                                                    <input
                                                                                        id="registration-team-number-{{ $registration->id }}"
                                                                                        class="form-control form-control-sm text-center"
                                                                                        type="number"
                                                                                        name="team_number"
                                                                                        value="{{ $registration->team_number }}"
                                                                                        min="1"
                                                                                        max="999"
                                                                                        inputmode="numeric"
                                                                                        data-original-value="{{ $registration->team_number }}"
                                                                                        aria-label="Numero de equipo de {{ $registration->team?->name ?? 'equipo' }}"
                                                                                    >
                                                                                    <div class="invalid-feedback" data-error-for="team_number"></div>
                                                                                </form>
                                                                            @else
                                                                                <span class="badge text-bg-light border text-body">{{ $registration->team_number }}</span>
                                                                            @endcan
                                                                        </td>
                                                                        <td class="fw-semibold">{{ $registration->team?->name ?? '-' }}</td>
                                                                        <td>{{ $registration->category?->name ?? '-' }}</td>
                                                                        <td>{{ $registration->seriesLabel() }}</td>
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
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-ui.table-card>
    </div>
@endsection
