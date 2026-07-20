@extends('layouts.admin')

@section('title', 'Tarjetas rojas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tarjetas rojas')
@section('page-subtitle', 'Jornadas disponibles para el comite de penas')

@section('content')
    <x-ui.table-card title="Jornadas finalizadas">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Jornada</th>
                    <th>Gestion</th>
                    <th>Fechas</th>
                    <th>Partidos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matchdays as $matchday)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $matchday->name }}</div>
                            <div class="text-body-secondary small">Nro. {{ $matchday->number }}</div>
                        </td>
                        <td>{{ $matchday->season?->name ?? '-' }}</td>
                        <td>{{ $matchday->dates_count }}</td>
                        <td>{{ $matchday->fixture_matches_count }}</td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm" href="{{ route('red-cards.matchdays.show', $matchday) }}">Ver partidos</a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="No hay jornadas finalizadas." />
                @endforelse
            </tbody>
        </table>
        <x-slot:footer>{{ $matchdays->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
