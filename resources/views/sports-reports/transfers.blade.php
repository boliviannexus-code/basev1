@extends('layouts.admin')

@section('title', 'Reporte pases | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de pases')
@section('page-subtitle', 'Filtro por torneo, categoria, equipo y fechas')

@section('content')
    @include('sports-reports.partials.tournament-category-filters', ['includeTeam' => true, 'includeDates' => true, 'pdfRoute' => route('sports-reports.transfers.pdf')])

    <x-ui.table-card title="Pases">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Cod. jugador</th>
                    <th>Jugador</th>
                    <th>Origen</th>
                    <th>Destino</th>
                    <th>Estado</th>
                    <th>Fecha/Hora</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="fw-semibold">{{ $row->code }}</td>
                        <td>{{ $row->player?->internal_code ?? '-' }}</td>
                        <td>{{ $row->player?->full_name ?? '-' }}</td>
                        <td>{{ $row->fromTeam?->name ?? '-' }}</td>
                        <td>{{ $row->toTeam?->name ?? '-' }}</td>
                        <td><span class="badge text-bg-{{ player_transfer_status_tone($row->status) }}">{{ player_transfer_status_label($row->status) }}</span></td>
                        <td>{{ $row->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ $row->requester?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="8" message="Selecciona filtros o no hay pases registrados." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
