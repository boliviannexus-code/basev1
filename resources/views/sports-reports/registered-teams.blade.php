@extends('layouts.admin')

@section('title', 'Reporte equipos inscritos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reporte de equipos inscritos')
@section('page-subtitle', 'Filtro por torneo y categoria')

@section('content')
    @include('sports-reports.partials.tournament-category-filters', ['categoryRequired' => true, 'pdfRoute' => route('sports-reports.registered-teams.pdf')])

    <x-ui.table-card title="Equipos inscritos">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nro.</th>
                    <th>Equipo</th>
                    <th>Torneo</th>
                    <th>Serie</th>
                    <th>Estado</th>
                    <th>Fecha/Hora</th>
                    <th>Usuario</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row->team_number ?? '-' }}</td>
                        <td class="fw-semibold">{{ $row->team?->name ?? '-' }}</td>
                        <td>{{ $row->tournament?->name ?? '-' }}</td>
                        <td>{{ $row->seriesLabel() }}</td>
                        <td><span class="badge text-bg-success">Inscrito</span></td>
                        <td>{{ $row->created_at?->format('d/m/Y H:i') ?? '-' }}</td>
                        <td>{{ $row->creator?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="Selecciona torneo y categoria o no hay equipos inscritos." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
