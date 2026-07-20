@extends('layouts.admin')

@section('title', 'Kardex de jugador | '.config('app.name', 'Base Admin'))
@section('page-title', 'Kardex de jugador')
@section('page-subtitle', 'Busqueda individual de historial deportivo')

@section('content')
    <x-ui.table-card title="Jugadores">
        <table class="table table-hover align-middle" data-datatable data-order='[[0,"asc"]]' data-page-length="25">
            <thead>
                <tr>
                    <th>Jugador</th>
                    <th>CI</th>
                    <th>Codigo</th>
                    <th>Nacimiento</th>
                    <th>Estado</th>
                    <th class="text-end">Imprimir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($playerOptions as $option)
                    <tr>
                        <td class="fw-semibold">{{ $option->full_name }}</td>
                        <td>{{ $option->ci ?? '-' }}</td>
                        <td>{{ $option->internal_code ?? '-' }}</td>
                        <td data-order="{{ $option->birth_date?->format('Ymd') ?? '0' }}">{{ $option->birth_date?->format('d/m/Y') ?? '-' }}</td>
                        <td>
                            <span class="badge text-bg-{{ $option->is_active ? 'success' : 'secondary' }}">
                                {{ $option->is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('sports-reports.player-kardex.pdf', ['player_id' => $option->id]) }}" target="_blank" rel="noopener">
                                Imprimir
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-ui.table-card>

    @if ($player)
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-center">
                    <div class="col-md-5">
                        <h2 class="h3 mb-1">{{ $player->full_name }}</h2>
                        <div class="text-body-secondary">CI {{ $player->ci ?? '-' }} · {{ $player->internal_code ?? '-' }}</div>
                    </div>
                    <div class="col-md-7">
                        <div class="row g-2">
                            <div class="col-6 col-md-3">
                                <div class="text-body-secondary small">Nacimiento</div>
                                <div class="fw-semibold">{{ $player->birth_date?->format('d/m/Y') ?? '-' }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-body-secondary small">Edad</div>
                                <div class="fw-semibold">{{ $player->age() ?? '-' }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-body-secondary small">Estado</div>
                                <div class="fw-semibold">{{ $player->is_active ? 'Activo' : 'Inactivo' }}</div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="text-body-secondary small">Liga</div>
                                <div class="fw-semibold">{{ $player->company?->name ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            @foreach ([
                'Equipos' => $context['summary']['teams'],
                'Habilitaciones' => $context['summary']['habilitations'],
                'Partidos' => $context['summary']['matches'],
                'Goles' => $context['summary']['goals'],
                'Amarillas' => $context['summary']['yellow_cards'],
                'Rojas' => $context['summary']['red_cards'],
                'Pases' => $context['summary']['transfers'],
                'Castigos' => $context['summary']['punishments'],
            ] as $label => $value)
                <div class="col-6 col-md-3 col-xl-2">
                    <div class="card h-100">
                        <div class="card-body py-3">
                            <div class="text-body-secondary small">{{ $label }}</div>
                            <div class="h4 mb-0">{{ $value }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @include('sports-reports.partials.kardex-section', ['title' => 'Historial de equipos', 'rows' => $context['teamHistory'], 'type' => 'teams'])

        <x-ui.table-card title="Resumen por torneo">
            <table class="table table-hover align-middle" data-datatable data-order='[[0,"asc"]]'>
                <thead>
                    <tr>
                        <th>Torneo</th>
                        <th>Equipos</th>
                        <th>Habilitaciones</th>
                        <th>Partidos</th>
                        <th>Goles</th>
                        <th>Amarillas</th>
                        <th>Rojas</th>
                        <th>Castigo</th>
                        <th>Pases</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($context['tournamentSummary'] as $row)
                        <tr>
                            <td class="fw-semibold">{{ $row['tournament'] }}</td>
                            <td>{{ $row['teams_label'] }}</td>
                            <td>{{ $row['habilitations'] }}</td>
                            <td>{{ $row['matches'] }}</td>
                            <td>{{ $row['goals'] }}</td>
                            <td>{{ $row['yellow_cards'] }}</td>
                            <td>{{ $row['red_cards'] }}</td>
                            <td>{{ $row['suspended_matches'] }}</td>
                            <td>{{ $row['transfers'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.table-card>

        <x-ui.table-card title="Detalles historicos del jugador">
            <table class="table table-hover align-middle" data-datatable data-order='[[0,"desc"]]' data-page-length="25">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Equipo</th>
                        <th>Competencia</th>
                        <th>Detalle</th>
                        <th>Datos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($context['details'] as $row)
                        <tr>
                            <td data-order="{{ $row['date']?->format('YmdHis') ?? '0' }}">{{ $row['date']?->format('d/m/Y H:i') ?? '-' }}</td>
                            <td><span class="badge text-bg-secondary">{{ $row['type'] }}</span></td>
                            <td>{{ $row['team'] }}</td>
                            <td>{{ $row['competition'] }}</td>
                            <td>{{ $row['detail'] }}</td>
                            <td>{{ $row['stats'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.table-card>
    @endif
@endsection
