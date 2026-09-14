@extends('layouts.admin')

@section('title', 'Registrar partido | '.config('app.name', 'Base Admin'))
@section('page-title', $report?->status === 'started' ? 'Confirmar datos iniciales' : 'Registrar partido')
@section('page-subtitle', ($match->matchdayDate?->matchday?->name ?? 'Jornada').' · '.($match->matchdayDate?->date?->format('d/m/Y') ?? '-'))

@section('content')
    @php
        $homeName = $match->homeTeam?->name ?? $match->home_seed ?? 'Equipo A';
        $awayName = $match->awayTeam?->name ?? $match->away_seed ?? 'Equipo B';
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('match-reports.matchdays.show', $match->matchdayDate->matchday) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Partidos
        </a>
        <span class="badge text-bg-success">Jornada finalizada</span>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="text-body-secondary small text-uppercase fw-semibold">Partido</div>
                    <h2 class="h2 mb-1">{{ $homeName }} <span class="text-body-secondary">vs</span> {{ $awayName }}</h2>
                    <div class="text-body-secondary">
                        {{ $match->category?->name ?? '-' }} · {{ $match->tournament?->name ?? '-' }}
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">{{ $match->scheduled_time ? \Carbon\Carbon::parse($match->scheduled_time)->format('H:i') : '-' }}</div>
                    <div class="text-body-secondary small">{{ $match->matchdayDate?->court?->name ?? 'Sin cancha' }}</div>
                </div>
            </div>
        </div>
    </div>

    @if ($report?->status === 'started')
        <div class="alert alert-info">
            El partido ya esta iniciado. Puedes corregir o confirmar estos datos; el marcador y las estadisticas registradas se conservaran.
        </div>
    @endif

    <form method="POST" action="{{ route('match-reports.matches.update', $match) }}">
        @csrf
        @method('PUT')

        <div class="match-report-entry">
            <div class="card match-report-section">
                <div class="card-header">
                    <div>
                        <h3 class="card-title mb-0">Arbitros</h3>
                        <div class="text-body-secondary small">Equipo arbitral asignado al partido</div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="referee-1">Arbitro 1</label>
                            <input class="form-control @error('referee_1') is-invalid @enderror" id="referee-1" name="referee_1" value="{{ old('referee_1', $report->referee_1 ?? '') }}">
                            @error('referee_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="referee-2">Arbitro 2</label>
                            <input class="form-control @error('referee_2') is-invalid @enderror" id="referee-2" name="referee_2" value="{{ old('referee_2', $report->referee_2 ?? '') }}">
                            @error('referee_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="referee-3">Arbitro 3</label>
                            <input class="form-control @error('referee_3') is-invalid @enderror" id="referee-3" name="referee_3" value="{{ old('referee_3', $report->referee_3 ?? '') }}">
                            @error('referee_3')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card match-report-section">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h3 class="card-title mb-0">Datos iniciales por equipo</h3>
                        <div class="text-body-secondary small">{{ $controlItems->count() }} controles activos para esta liga</div>
                    </div>
                    <span class="badge text-bg-light border">Local / Visitante</span>
                </div>
                <div class="card-body p-0">
                    <div class="match-control-scroll">
                        <table class="table table-vcenter match-control-table mb-0">
                        <thead>
                            <tr>
                                <th class="match-control-team-col">Equipo</th>
                                @foreach ($controlItems as $item)
                                    <th class="text-center">
                                        <span>{{ $item['label'] }}</span>
                                        @if((float) ($item['absence_cost'] ?? 0) > 0)
                                            <small>Falta: Bs {{ number_format((float) $item['absence_cost'], 2, ',', '.') }}</small>
                                        @endif
                                        @if ($item['is_universal'])
                                            <small>Universal</small>
                                        @endif
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="match-control-team-col">
                                    <div class="fw-semibold text-truncate">{{ $homeName }}</div>
                                    <div class="text-body-secondary small">Local</div>
                                </td>
                                @foreach ($controlItems as $item)
                                    <td class="text-center">
                                        <input type="hidden" name="control_items[home][{{ $item['key'] }}]" value="0">
                                        <input class="form-check-input match-control-check" name="control_items[home][{{ $item['key'] }}]" type="checkbox" value="1" aria-label="{{ $homeName }} - {{ $item['label'] }}" @checked(old('control_items.home.'.$item['key'], data_get($controlValues, 'home.'.$item['key'], true)))>
                                    </td>
                                @endforeach
                            </tr>
                            <tr>
                                <td class="match-control-team-col">
                                    <div class="fw-semibold text-truncate">{{ $awayName }}</div>
                                    <div class="text-body-secondary small">Visitante</div>
                                </td>
                                @foreach ($controlItems as $item)
                                    <td class="text-center">
                                        <input type="hidden" name="control_items[away][{{ $item['key'] }}]" value="0">
                                        <input class="form-check-input match-control-check" name="control_items[away][{{ $item['key'] }}]" type="checkbox" value="1" aria-label="{{ $awayName }} - {{ $item['label'] }}" @checked(old('control_items.away.'.$item['key'], data_get($controlValues, 'away.'.$item['key'], true)))>
                                    </td>
                                @endforeach
                            </tr>
                        </tbody>
                    </table>
                    </div>

                    <div class="p-3 border-top">
                        <label class="form-label" for="match-report-notes">Observaciones iniciales</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="match-report-notes" name="notes" rows="3">{{ old('notes', $report->notes ?? '') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a class="btn btn-outline-secondary" href="{{ $report?->status === 'started' ? route('match-reports.matches.play', $match) : route('match-reports.matchdays.show', $match->matchdayDate->matchday) }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy me-1"></i>
                {{ $report?->status === 'started' ? 'Confirmar y volver al partido' : 'Guardar registro inicial' }}
            </button>
        </div>
    </form>
@endsection
