@extends('layouts.admin')

@section('title', 'Registro de partidos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Registro de partidos')
@section('page-subtitle', 'Jornadas finalizadas listas para registrar datos de partido')

@section('content')
    <x-ui.table-card title="Jornadas finalizadas">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Jornada</th>
                    <th>Gestion</th>
                    <th class="text-center">Fechas</th>
                    <th class="text-center">Partidos</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matchdays as $matchday)
                    <tr>
                        <td>
                            <span class="fw-semibold">{{ $matchday->name ?? 'Jornada '.$matchday->number }}</span>
                            <div class="text-body-secondary small">Finalizada</div>
                        </td>
                        <td>{{ $matchday->season?->name ?? '-' }}</td>
                        <td class="text-center"><span class="badge text-bg-light border text-body">{{ $matchday->dates_count }}</span></td>
                        <td class="text-center"><span class="badge text-bg-light border text-body">{{ $matchday->fixture_matches_count }}</span></td>
                        <td class="text-end">
                            <a class="btn btn-primary btn-sm" href="{{ route('match-reports.matchdays.show', $matchday) }}">
                                Ver partidos
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="Aun no hay jornadas finalizadas para registrar partidos." />
                @endforelse
            </tbody>
        </table>

        <div class="mt-3">
            {{ $matchdays->links() }}
        </div>
    </x-ui.table-card>
@endsection
