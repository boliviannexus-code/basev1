@extends('layouts.admin')

@section('title', 'Reporte jornadas finalizadas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de jornadas finalizadas')
@section('page-subtitle', 'Filtro por gestion')

@section('content')
    <form class="card mb-3" method="GET">
        <div class="card-body">
            <label class="form-label" for="report-season">Gestion</label>
            <select class="form-select" id="report-season" name="season_id" data-tom-select data-placeholder="Seleccionar gestion" onchange="this.form.submit()">
                @foreach ($seasons as $season)
                    <option value="{{ $season->id }}" @selected((int) request('season_id', $filters['season']?->id) === (int) $season->id)>{{ $season->name }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <x-ui.table-card title="Jornadas finalizadas">
        <table class="table table-hover align-middle" data-datatable data-order='[[0,"desc"]]'>
            <thead>
                <tr>
                    <th>Nro.</th>
                    <th>Jornada</th>
                    <th>Gestion</th>
                    <th>Fecha programada</th>
                    <th>Dias</th>
                    <th>Partidos</th>
                    <th>Estado</th>
                    <th class="text-end">Imprimir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->number ?? '-' }}</td>
                        <td class="fw-semibold">{{ $row->name }}</td>
                        <td>{{ $row->season?->name ?? '-' }}</td>
                        <td data-order="{{ $row->scheduled_date?->format('Ymd') ?? '0' }}">{{ $row->scheduled_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $row->dates_count }}</td>
                        <td>{{ $row->fixture_matches_count }}</td>
                        <td><span class="badge text-bg-success">Finalizada</span></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-outline-primary" href="{{ route('sports-reports.finalized-matchdays.results-pdf', $row) }}" target="_blank" rel="noopener">Resultados</a>
                                <a class="btn btn-outline-warning" href="{{ route('sports-reports.yellow-cards.pdf', ['season_id' => $row->season_id, 'matchday_id' => $row->id]) }}" target="_blank" rel="noopener">Amarillas</a>
                                <a class="btn btn-outline-danger" href="{{ route('sports-reports.red-cards.pdf', ['season_id' => $row->season_id, 'matchday_id' => $row->id]) }}" target="_blank" rel="noopener">Rojas</a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
