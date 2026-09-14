@extends('layouts.admin')

@section('title', 'Respaldos de base de datos | '.config('app.name', 'Base Admin'))
@section('page-title', 'Respaldos de base de datos')
@section('page-subtitle', 'Crear, descargar y restaurar copias de seguridad')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-ui.table-card title="Respaldos disponibles">
                <x-slot:actions>
                    @can('database-backups.create')
                        <form method="POST" action="{{ route('database-backups.store') }}">
                            @csrf
                            <button class="btn btn-primary btn-sm" type="submit">
                                <i class="ti ti-database-export me-1"></i> Generar respaldo
                            </button>
                        </form>
                    @endcan
                </x-slot:actions>

                <table class="table table-hover align-middle">
                    <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tamano</th>
                        <th>Fecha</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($backups as $backup)
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $backup['name'] }}</div>
                                <div class="text-body-secondary small">{{ $backup['path'] }}</div>
                            </td>
                            <td>{{ number_format($backup['size'] / 1024, 2) }} KB</td>
                            <td>{{ $backup['last_modified']->format('Y-m-d H:i') }}</td>
                            <td class="text-end">
                                <a class="btn btn-outline-secondary btn-sm" href="{{ route('database-backups.download', $backup['name']) }}">
                                    <i class="ti ti-download me-1"></i> Descargar
                                </a>
                                @can('database-backups.restore')
                                    <form class="d-inline" method="POST" action="{{ route('database-backups.restore') }}" data-confirm-delete="Restaurar este respaldo reemplazara la base de datos actual. Continuar?">
                                        @csrf
                                        <input type="hidden" name="backup" value="{{ $backup['name'] }}">
                                        <button class="btn btn-outline-warning btn-sm" type="submit">
                                            <i class="ti ti-database-import me-1"></i> Restaurar
                                        </button>
                                    </form>
                                @endcan
                                @can('database-backups.delete')
                                    <form class="d-inline" method="POST" action="{{ route('database-backups.destroy', $backup['name']) }}" data-confirm-delete="Eliminar respaldo?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger btn-sm" type="submit">
                                            <i class="ti ti-trash me-1"></i> Eliminar
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="4" message="No hay respaldos generados." />
                    @endforelse
                    </tbody>
                </table>
            </x-ui.table-card>
        </div>

        <div class="col-lg-4">
            <x-ui.card title="Restaurar desde archivo">
                <div class="mb-3 text-body-secondary small">
                    Conexion actual: <span class="fw-semibold">{{ $driver }}</span>
                </div>

                @can('database-backups.restore')
                    <form method="POST" action="{{ route('database-backups.restore') }}" enctype="multipart/form-data" data-confirm-delete="Restaurar este archivo reemplazara la base de datos actual. Continuar?">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="backupFile">Archivo SQL</label>
                            <input class="form-control" id="backupFile" name="file" type="file" accept=".sql,.txt" required>
                            <div class="form-hint">Tamano maximo: 100 MB.</div>
                        </div>
                        <button class="btn btn-warning w-100" type="submit">
                            <i class="ti ti-database-import me-1"></i> Restaurar base de datos
                        </button>
                    </form>
                @else
                    <div class="alert alert-info mb-0">No tienes permiso para restaurar respaldos.</div>
                @endcan
            </x-ui.card>
        </div>
    </div>
@endsection
