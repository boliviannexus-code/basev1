@extends('layouts.admin')

@section('title', 'Reporte jugadores habilitados | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de jugadores habilitados')
@section('page-subtitle', 'Filtro por torneo, categoria y equipo')

@section('content')
    @include('sports-reports.partials.tournament-category-filters', ['includeTeam' => true, 'categoryRequired' => true, 'pdfRoute' => route('sports-reports.enabled-players.pdf')])

    <x-ui.table-card title="Jugadores habilitados">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Equipo</th>
                    <th>Cod. jugador</th>
                    <th>Jugador</th>
                    <th>CI</th>
                    <th>Fecha/Hora</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->team?->name ?? '-' }}</td>
                        <td>{{ $row->player?->internal_code ?? '-' }}</td>
                        <td class="fw-semibold">{{ $row->player?->full_name ?? '-' }}</td>
                        <td>{{ $row->player?->ci ?? '-' }}</td>
                        <td>{{ $row->enabled_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ $row->enabledBy?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="6" message="Selecciona torneo y categoria o no hay jugadores habilitados." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
