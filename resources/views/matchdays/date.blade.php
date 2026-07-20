@extends('layouts.admin')

@section('title', 'Configurar fecha | '.config('app.name', 'Base Admin'))
@section('page-title', 'Configurar fecha')
@section('page-subtitle', ($matchday->name ?? 'Jornada '.$matchday->number).' · '.$date->date->format('d/m/Y').' · '.($date->court?->name ?? 'Sin cancha'))

@section('content')
    @php
        $dayName = ucfirst($date->date->copy()->locale('es')->translatedFormat('l'));
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('matchdays.configure', $matchday) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Fechas
        </a>
        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#matchday-fiscals-modal">
            <i class="ti ti-user-check me-1"></i>
            Agregar Ver fiscales
        </button>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div class="d-flex flex-wrap align-items-center gap-3">
                    <div>
                        <div class="text-body-secondary small text-uppercase fw-semibold">Fecha de jornada</div>
                        <h2 class="h2 mb-0">{{ $dayName }}</h2>
                    </div>
                    <div class="text-body-secondary">{{ $date->date->format('d/m/Y') }} · {{ $matchday->name ?? 'Jornada '.$matchday->number }}</div>
                    <div class="fw-semibold">
                        <i class="ti ti-map-pin me-1"></i>
                        {{ $date->court?->name ?? 'Sin cancha asignada' }}
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-{{ $matchday->status === 'finalized' ? 'success' : 'light border text-body' }}">{{ $matchday->status === 'finalized' ? 'Jornada finalizada' : 'Borrador' }}</span>
                    <span class="badge text-bg-light border text-body">{{ $scheduledMatches->count() }} partido(s) programado(s)</span>
                    <span class="badge text-bg-light border text-body">{{ $fiscalAssignments->count() }} fiscalia(s)</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-ui.table-card title="Seleccionar partidos del fixture">
                <form class="row g-2 mb-3" method="GET" action="{{ route('matchdays.dates.configure', $date) }}" data-matchday-fixture-filter data-options-url="{{ route('matchdays.dates.matches.options', $date) }}">
                    <div class="col-md-6">
                        <label class="form-label" for="matchday-tournament">Torneo</label>
                        <select class="form-select" id="matchday-tournament" name="tournament_id" data-fixture-tournament-select>
                            <option value="">Seleccionar</option>
                            @foreach ($tournaments as $tournament)
                                <option value="{{ $tournament->id }}" @selected((int) $selectedTournamentId === (int) $tournament->id)>
                                    {{ $tournament->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="matchday-fixture-group">Categoria y serie</label>
                        <select class="form-select" id="matchday-fixture-group" name="fixture_group" data-fixture-group-select @disabled(! $selectedTournamentId)>
                            <option value="">Seleccionar</option>
                            @foreach ($fixtureGroups as $group)
                                <option value="{{ $group['value'] }}" @selected($selectedFixtureGroup === $group['value'])>
                                    {{ $group['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>

                <div data-fixture-matches-container>
                    @include('matchdays.partials.available-fixture-matches')
                </div>
            </x-ui.table-card>
        </div>

        <div class="col-xl-6">
            <x-ui.table-card title="Partidos programados">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Horario</th>
                            <th>Partido</th>
                            <th class="text-end">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($scheduledMatches as $match)
                            <tr data-scheduled-match-row>
                                <td style="width: 8.5rem;">
                                    @if ($matchday->status === 'finalized')
                                        <span class="fw-semibold">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</span>
                                    @else
                                    @can('matchdays.update')
                                        <form class="d-flex gap-2" method="POST" action="{{ route('matchdays.dates.matches.time', ['date' => $date, 'fixtureMatch' => $match]) }}" data-schedule-time-form>
                                            @csrf
                                            @method('PATCH')
                                            <input class="form-control form-control-sm px-1" name="scheduled_time" type="time" value="{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '' }}" data-original-value="{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '' }}" data-schedule-time-input required>
                                            <button class="btn btn-outline-primary btn-icon btn-sm" type="submit" title="Guardar horario" data-schedule-time-save>
                                                <i class="ti ti-device-floppy"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="fw-semibold">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</span>
                                    @endcan
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $match->homeTeam?->name ?? $match->home_seed ?? 'Por definir' }}</span>
                                    <span class="text-body-secondary mx-1">vs</span>
                                    <span class="fw-semibold">{{ $match->awayTeam?->name ?? $match->away_seed ?? 'Por definir' }}</span>
                                    <div class="text-body-secondary small">
                                        {{ $match->category?->name ?? '-' }}
                                    </div>
                                </td>
                                <td class="text-end">
                                    @if ($matchday->status === 'finalized')
                                        <span class="text-body-secondary small">Finalizada</span>
                                    @else
                                    @can('matchdays.update')
                                        <form method="POST" action="{{ route('matchdays.dates.matches.destroy', ['date' => $date, 'fixtureMatch' => $match]) }}" data-confirm-delete="Eliminar programacion?" data-confirm-button-text="Si, quitar" data-confirm-text="El partido volvera a estar disponible para programarse." data-confirm-color="#dc3545">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-icon btn-sm" type="submit" title="Eliminar programacion">
                                                <i class="ti ti-trash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-body-secondary small">-</span>
                                    @endcan
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="3" message="Aun no hay partidos programados en esta fecha." />
                        @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

    </div>

    <div class="modal modal-blur fade" id="matchday-fiscals-modal" tabindex="-1" aria-labelledby="matchday-fiscals-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title" id="matchday-fiscals-modal-title">Fiscalias por horario</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3" data-matchday-fiscal-filter data-options-url="{{ route('matchdays.dates.fiscals.options', $date) }}">
                        <div class="col-md-6">
                            <label class="form-label" for="fiscal-tournament">Torneo</label>
                            <select class="form-select" id="fiscal-tournament" data-fiscal-tournament-select>
                                <option value="">Seleccionar</option>
                                @foreach ($tournaments as $tournament)
                                    <option value="{{ $tournament->id }}" @selected((int) $selectedFiscalTournamentId === (int) $tournament->id)>
                                        {{ $tournament->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="fiscal-fixture-group">Categoria y serie</label>
                            <select class="form-select" id="fiscal-fixture-group" data-fiscal-group-select @disabled(! $selectedFiscalTournamentId)>
                                <option value="">Seleccionar</option>
                                @foreach ($fiscalFixtureGroups as $group)
                                    <option value="{{ $group['value'] }}" @selected($selectedFiscalFixtureGroup === $group['value'])>
                                        {{ $group['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="alert alert-info {{ $selectedFiscalTournamentId && $selectedFiscalFixtureGroup ? 'd-none' : '' }}" data-fiscal-filter-empty>
                            Selecciona torneo, categoria y serie para registrar fiscales de turno.
                    </div>
                        @error('team_id')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror
                        @error('start_time')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror
                        @error('end_time')
                            <div class="alert alert-danger py-2">{{ $message }}</div>
                        @enderror

                        @if ($matchday->status !== 'finalized')
                            @can('matchdays.update')
                                <form class="row g-2 align-items-end mb-3" method="POST" data-fiscal-assignment-form action="{{ route('matchdays.dates.fiscals.store', array_filter([
                                    'date' => $date,
                                    'tournament_id' => $selectedTournamentId,
                                    'fixture_group' => $selectedFixtureGroup,
                                    'category_id' => $selectedCategoryId,
                                    'series' => $selectedSeries,
                                    'fiscal_tournament_id' => $selectedFiscalTournamentId,
                                    'fiscal_fixture_group' => $selectedFiscalFixtureGroup,
                                    'fiscal_category_id' => $selectedFiscalCategoryId,
                                    'fiscal_series' => $selectedFiscalSeries,
                                    'show_fiscals' => 1,
                                ])) }}">
                                    @csrf
                                    <input type="hidden" name="fiscal_tournament_id" value="{{ $selectedFiscalTournamentId }}">
                                    <input type="hidden" name="fiscal_fixture_group" value="{{ $selectedFiscalFixtureGroup }}">
                                    <input type="hidden" name="fiscal_category_id" value="{{ $selectedFiscalCategoryId }}">
                                    <input type="hidden" name="fiscal_series" value="{{ $selectedFiscalSeries }}">
                                    <div class="col-md-3">
                                        <label class="form-label" for="fiscal-start-time">Desde</label>
                                        <input class="form-control" id="fiscal-start-time" name="start_time" type="time" value="{{ old('start_time') }}" required @disabled(! $selectedFiscalFixtureGroup)>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="fiscal-end-time">Hasta</label>
                                        <input class="form-control" id="fiscal-end-time" name="end_time" type="time" value="{{ old('end_time') }}" required @disabled(! $selectedFiscalFixtureGroup)>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="fiscal-team">Fiscal</label>
                                        <select class="form-select" id="fiscal-team" name="team_id" data-fiscal-team-select required @disabled(! $selectedFiscalFixtureGroup)>
                                            <option value="">Seleccionar fiscal</option>
                                            @foreach ($fiscalCandidates as $candidate)
                                                <option value="{{ $candidate['team_id'] }}" @selected((int) old('team_id') === (int) $candidate['team_id'])>
                                                    {{ $candidate['team_name'] }} ({{ $candidate['fiscal_count'] }} fiscalia(s) en la gestion)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-1 text-end">
                                        <button class="btn btn-primary btn-icon" type="submit" title="Guardar fiscalia" aria-label="Guardar fiscalia" @disabled(! $selectedFiscalFixtureGroup)>
                                            <i class="ti ti-device-floppy"></i>
                                        </button>
                                    </div>
                                </form>
                            @endcan
                        @endif

                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 10rem;">Horario</th>
                                    <th>Fiscal</th>
                                    <th class="text-end" style="width: 6rem;">Accion</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($fiscalAssignments as $fiscal)
                                    <tr>
                                        <td class="fw-semibold">
                                            {{ \Carbon\Carbon::parse($fiscal->start_time)->format('H:i') }} - {{ \Carbon\Carbon::parse($fiscal->end_time)->format('H:i') }}
                                        </td>
                                        <td>{{ $fiscal->team?->name ?? '-' }}</td>
                                        <td class="text-end">
                                            @if ($matchday->status !== 'finalized')
                                                @can('matchdays.update')
                                                    <form method="POST" action="{{ route('matchdays.dates.fiscals.destroy', array_filter([
                                                        'date' => $date,
                                                        'fiscal' => $fiscal,
                                                        'tournament_id' => $selectedTournamentId,
                                                        'fixture_group' => $selectedFixtureGroup,
                                                        'category_id' => $selectedCategoryId,
                                                        'series' => $selectedSeries,
                                                        'fiscal_tournament_id' => $selectedFiscalTournamentId,
                                                        'fiscal_fixture_group' => $selectedFiscalFixtureGroup,
                                                        'fiscal_category_id' => $selectedFiscalCategoryId,
                                                        'fiscal_series' => $selectedFiscalSeries,
                                                        'show_fiscals' => 1,
                                                    ])) }}" data-confirm-delete="Eliminar fiscalia?" data-confirm-button-text="Si, eliminar" data-confirm-text="Los partidos de ese horario quedaran sin fiscal asignado." data-confirm-color="#dc3545">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-icon btn-sm" type="submit" title="Eliminar fiscalia" aria-label="Eliminar fiscalia">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <x-ui.empty-row colspan="3" message="Aun no hay fiscalias registradas para esta fecha." />
                                @endforelse
                            </tbody>
                        </table>
                </div>
            </div>
        </div>
    </div>

    @if (request()->boolean('show_fiscals') || $errors->has('team_id') || $errors->has('start_time') || $errors->has('end_time'))
        @push('scripts')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const modal = document.getElementById('matchday-fiscals-modal');

                    if (modal && window.bootstrap) {
                        window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    }
                });
            </script>
        @endpush
    @endif
@endsection
