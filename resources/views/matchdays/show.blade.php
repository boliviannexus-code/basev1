@extends('layouts.admin')

@section('title', 'Jornadas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Jornadas')
@section('page-subtitle', $season->name.' · '.($season->company?->name ?? '-'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('matchdays.index') }}">
            <i class="ti ti-arrow-left me-1"></i>
            Gestiones
        </a>
        @can('matchdays.create')
            <form method="POST" action="{{ route('matchdays.store', $season) }}">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit">
                    <i class="ti ti-plus me-1"></i>
                    Crear jornada
                </button>
            </form>
        @endcan
    </div>

    <x-ui.table-card title="Jornadas de la gestion">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Jornada</th>
                    <th>Fechas</th>
                    <th>Estado</th>
                    <th>Partidos</th>
                    <th class="text-end">Accion</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($matchdays as $matchday)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $matchday->name ?? 'Jornada '.$matchday->number }}</div>
                            <div class="text-body-secondary small">Numero {{ $matchday->number }}</div>
                        </td>
                        <td>{{ $matchday->dates_count }} fecha(s)</td>
                        <td>
                            <span class="badge text-bg-{{ $matchday->status === 'finalized' ? 'success' : 'secondary' }}">
                                {{ $matchday->status === 'finalized' ? 'Finalizada' : 'Borrador' }}
                            </span>
                        </td>
                        <td>{{ $matchday->fixture_matches_count }} partido(s)</td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('matchdays.configure', $matchday) }}">
                                Configurar
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="5" message="Aun no hay jornadas creadas para esta gestion." />
                @endforelse
            </tbody>
        </table>
    </x-ui.table-card>
@endsection
