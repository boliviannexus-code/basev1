@extends('layouts.admin')

@section('title', 'Reporte tarjetas amarillas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de tarjetas amarillas por jornada')
@section('page-subtitle', 'Filtro por gestion y jornada')

@section('content')
    @include('sports-reports.partials.matchday-filters', ['includeMatchday' => true, 'pdfRoute' => route('sports-reports.yellow-cards.pdf')])

    <x-ui.table-card title="Tarjetas amarillas">
        <table class="table table-hover align-middle" data-datatable data-order='[[0,"asc"]]'>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Torneo</th>
                    <th>Categoria</th>
                    <th>Partido</th>
                    <th>Equipo</th>
                    <th>Jugador</th>
                    <th>TA</th>
                    <th>Acum. torneo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['date'] }}</td>
                        <td>{{ $row['tournament'] }}</td>
                        <td>{{ $row['category'] }}</td>
                        <td>{{ $row['match'] }}</td>
                        <td>{{ $row['team'] }}</td>
                        <td class="fw-semibold">{{ $row['player'] }}</td>
                        <td><span class="badge text-bg-warning">{{ $row['yellow_cards'] }}</span></td>
                        <td>{{ $row['tournament_yellow_cards'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
