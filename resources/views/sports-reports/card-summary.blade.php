@extends('layouts.admin')

@section('title', 'Acumulado tarjetas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Acumulado de tarjetas por categoria')
@section('page-subtitle', 'Totales de amarillas y rojas por categoria')

@section('content')
    @include('sports-reports.partials.matchday-filters', ['pdfRoute' => route('sports-reports.card-summary.pdf')])

    <x-ui.table-card title="Acumulado por categoria">
        <table class="table table-hover align-middle" data-datatable data-order='[[0,"asc"]]'>
            <thead>
                <tr>
                    <th>Categoria</th>
                    <th>Amarillas</th>
                    <th>Rojas</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row['category'] }}</td>
                        <td><span class="badge text-bg-warning">{{ $row['yellow_cards'] }}</span></td>
                        <td><span class="badge text-bg-danger">{{ $row['red_cards'] }}</span></td>
                        <td>{{ $row['total'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
