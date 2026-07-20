@extends('layouts.admin')

@section('title', 'Registrar partido | '.config('app.name', 'Base Admin'))
@section('page-title', 'Registrar partido')
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

    <form method="POST" action="{{ route('match-reports.matches.update', $match) }}">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-5">
                <x-ui.table-card title="Arbitros">
                    <div class="mb-3">
                        <label class="form-label" for="referee-1">Arbitro 1</label>
                        <input class="form-control @error('referee_1') is-invalid @enderror" id="referee-1" name="referee_1" value="{{ old('referee_1', $report->referee_1 ?? '') }}">
                        @error('referee_1')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="referee-2">Arbitro 2</label>
                        <input class="form-control @error('referee_2') is-invalid @enderror" id="referee-2" name="referee_2" value="{{ old('referee_2', $report->referee_2 ?? '') }}">
                        @error('referee_2')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="referee-3">Arbitro 3</label>
                        <input class="form-control @error('referee_3') is-invalid @enderror" id="referee-3" name="referee_3" value="{{ old('referee_3', $report->referee_3 ?? '') }}">
                        @error('referee_3')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </x-ui.table-card>
            </div>

            <div class="col-lg-7">
                <x-ui.table-card title="Datos iniciales por equipo">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Equipo</th>
                                <th class="text-center">Trajo balon</th>
                                <th class="text-center">Presente</th>
                                <th class="text-center">Pago cancha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="fw-semibold">{{ $homeName }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="home_brought_ball" value="0">
                                    <input class="form-check-input" name="home_brought_ball" type="checkbox" value="1" @checked(old('home_brought_ball', $report ? $report->home_brought_ball : true))>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="home_present" value="0">
                                    <input class="form-check-input" name="home_present" type="checkbox" value="1" @checked(old('home_present', $report ? $report->home_present : true))>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="home_paid_court_fee" value="0">
                                    <input class="form-check-input" name="home_paid_court_fee" type="checkbox" value="1" @checked(old('home_paid_court_fee', $report ? $report->home_paid_court_fee : true))>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-semibold">{{ $awayName }}</td>
                                <td class="text-center">
                                    <input type="hidden" name="away_brought_ball" value="0">
                                    <input class="form-check-input" name="away_brought_ball" type="checkbox" value="1" @checked(old('away_brought_ball', $report ? $report->away_brought_ball : true))>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="away_present" value="0">
                                    <input class="form-check-input" name="away_present" type="checkbox" value="1" @checked(old('away_present', $report ? $report->away_present : true))>
                                </td>
                                <td class="text-center">
                                    <input type="hidden" name="away_paid_court_fee" value="0">
                                    <input class="form-check-input" name="away_paid_court_fee" type="checkbox" value="1" @checked(old('away_paid_court_fee', $report ? $report->away_paid_court_fee : true))>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="mb-0">
                        <label class="form-label" for="match-report-notes">Observaciones iniciales</label>
                        <textarea class="form-control @error('notes') is-invalid @enderror" id="match-report-notes" name="notes" rows="3">{{ old('notes', $report->notes ?? '') }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </x-ui.table-card>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-3">
            <a class="btn btn-outline-secondary" href="{{ route('match-reports.matchdays.show', $match->matchdayDate->matchday) }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">
                <i class="ti ti-device-floppy me-1"></i>
                Guardar registro inicial
            </button>
        </div>
    </form>
@endsection
