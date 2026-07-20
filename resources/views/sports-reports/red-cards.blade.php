@extends('layouts.admin')

@section('title', 'Reporte tarjetas rojas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de tarjetas rojas por jornada')
@section('page-subtitle', 'Filtro por gestion y jornada')

@section('content')
    @include('sports-reports.partials.matchday-filters', ['includeMatchday' => true, 'pdfRoute' => route('sports-reports.red-cards.pdf')])

    <x-ui.table-card title="Tarjetas rojas">
        <table class="table table-hover align-middle" data-datatable data-order='[[0,"asc"]]'>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Torneo</th>
                    <th>Categoria</th>
                    <th>Partido</th>
                    <th>Equipo</th>
                    <th>Jugador</th>
                    <th>TR</th>
                    <th>Acum. torneo</th>
                    <th>Articulo</th>
                    <th>Detalle</th>
                    <th>Part.</th>
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
                        <td><span class="badge text-bg-danger">{{ $row['red_cards'] }}</span></td>
                        <td>{{ $row['tournament_red_cards'] }}</td>
                        <td>Art. {{ $row['article'] }}</td>
                        <td>{{ $row['detail'] }}</td>
                        <td>{{ $row['suspended_matches'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
