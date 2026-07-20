@extends('layouts.admin')

@section('title', 'Importar jugadores | '.config('app.name', 'Base Admin'))
@section('page-title', 'Importar jugadores')
@section('page-subtitle', 'Carga masiva de planillas desde CSV')

@section('content')
    @php
        $report = session('import_report');
        $summary = $report['summary'] ?? null;
        $rows = $report['rows'] ?? [];
        $errorRows = collect($rows)->where('status', 'skipped')->values();
    @endphp

    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.card title="Archivo CSV">
                <form method="POST" action="{{ route('player-imports.store') }}" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="player-import-file">Archivo</label>
                        <input class="form-control @error('file') is-invalid @enderror" id="player-import-file" name="file" type="file" accept=".csv,text/csv,text/plain" required>
                        <div class="form-hint">Columnas: nombre, paterno, materno, ci, fecha de nacimiento, equipo, division.</div>
                        @error('file')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <label class="form-check mb-3">
                        <input class="form-check-input" name="dry_run" type="checkbox" value="1" checked>
                        <span class="form-check-label">Simular sin guardar cambios</span>
                    </label>

                    <div class="d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">Procesar archivo</button>
                    </div>
                </form>
            </x-ui.card>
        </div>

        <div class="col-lg-7">
            <x-ui.card title="Reglas de importacion">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold">Equipos</div>
                            <div class="text-body-secondary small">Se reutiliza el equipo si coincide por nombre fuerte; si no existe, se crea en mayusculas.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold">Jugadores</div>
                            <div class="text-body-secondary small">Se reutiliza por CI dentro de la liga activa; si no existe, se crea con codigo automatico.</div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="border rounded p-3">
                            <div class="fw-semibold">Plantilla</div>
                            <div class="text-body-secondary small">Un jugador solo puede estar activo en un equipo por division. En otra division puede pertenecer a otro equipo.</div>
                        </div>
                    </div>
                </div>
            </x-ui.card>
        </div>
    </div>

    @if ($summary)
        <x-ui.table-card title="{{ $summary['mode'] === 'dry_run' ? 'Resultado de simulacion' : 'Resultado de importacion' }}" class="mt-3">
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-body-secondary small">Filas</div>
                        <div class="h3 mb-0">{{ $summary['total_rows'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-body-secondary small">Procesadas</div>
                        <div class="h3 mb-0 text-success">{{ $summary['imported_rows'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-body-secondary small">Errores</div>
                        <div class="h3 mb-0 text-danger">{{ $summary['failed_rows'] }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="border rounded p-2">
                        <div class="text-body-secondary small">Duplicados ignorados</div>
                        <div class="h3 mb-0">{{ $summary['duplicate_rows_ignored'] ?? 0 }}</div>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <span class="badge text-bg-primary me-1">{{ $summary['players_created'] }}</span> jugadores nuevos
                    <span class="badge text-bg-secondary ms-2 me-1">{{ $summary['players_existing'] }}</span> existentes
                </div>
                <div class="col-md-4">
                    <span class="badge text-bg-primary me-1">{{ $summary['teams_created'] }}</span> equipos nuevos
                    <span class="badge text-bg-secondary ms-2 me-1">{{ $summary['teams_existing'] }}</span> existentes
                </div>
                <div class="col-md-4">
                    <span class="badge text-bg-primary me-1">{{ $summary['affiliations_created'] }}</span> plantillas nuevas
                    <span class="badge text-bg-secondary ms-2 me-1">{{ $summary['affiliations_existing'] }}</span> existentes
                </div>
            </div>

            @if ($errorRows->isEmpty())
                <div class="alert alert-success mb-0">No se encontraron errores en el archivo.</div>
            @else
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Fila</th>
                            <th>Jugador</th>
                            <th>Equipo</th>
                            <th>Division</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($errorRows as $row)
                            <tr>
                                <td>{{ $row['row'] }}</td>
                                <td>{{ $row['player'] ?? '-' }}</td>
                                <td>{{ $row['team'] ?? '-' }}</td>
                                <td>{{ $row['division'] ?? '-' }}</td>
                                <td>{{ implode(' ', $row['messages'] ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.table-card>
    @endif
@endsection
