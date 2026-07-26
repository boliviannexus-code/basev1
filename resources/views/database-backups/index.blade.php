@extends('layouts.admin')

@section('title', 'Respaldos de base de datos')

@section('content')
    <div class="page-header d-print-none">
        <div class="container-xl">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h2 class="page-title">Respaldos de base de datos</h2>
                </div>
                <div class="col-auto ms-auto">
                    <form method="POST" action="{{ route('database-backups.store') }}">
                        @csrf
                        <button class="btn btn-primary" type="submit">
                            <i class="ti ti-database-export me-1"></i>
                            Generar respaldo SQL
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="page-body">
        <div class="container-xl">
            <div class="row row-cards">
                <div class="col-xl-8">
                    <x-ui.table-card title="Respaldos generados">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Archivo</th>
                                        <th class="text-end">Tamano</th>
                                        <th>Fecha</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($backups as $backup)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $backup['name'] }}</div>
                                            </td>
                                            <td class="text-end">{{ number_format($backup['size'] / 1024, 1) }} KB</td>
                                            <td>{{ $backup['created_at']?->format('d/m/Y H:i') ?? 'Sin fecha' }}</td>
                                            <td class="text-end">
                                                <a class="btn btn-outline-primary btn-sm" href="{{ route('database-backups.download', $backup['name']) }}">
                                                    <i class="ti ti-download me-1"></i>Descargar
                                                </a>
                                                <form class="d-inline" method="POST" action="{{ route('database-backups.restore', $backup['name']) }}">
                                                    @csrf
                                                    <input type="hidden" name="confirm_restore" value="1">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit" data-confirm-delete="Restaurar este respaldo reemplazara los datos actuales. Continuar?">
                                                        <i class="ti ti-database-import me-1"></i>Restaurar
                                                    </button>
                                                </form>
                                                <form class="d-inline" method="POST" action="{{ route('database-backups.destroy', $backup['name']) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-outline-secondary btn-sm" type="submit" data-confirm-delete="Eliminar este respaldo?">
                                                        <i class="ti ti-trash me-1"></i>Eliminar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <x-ui.empty-row colspan="4" message="Aun no hay respaldos generados." />
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </x-ui.table-card>
                </div>

                <div class="col-xl-4">
                    <x-ui.card title="Restaurar desde archivo">
                        <div class="card-body">
                            <form method="POST" action="{{ route('database-backups.restore-upload') }}" enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="backup-upload">Archivo SQL</label>
                                    <input id="backup-upload" class="form-control @error('backup') is-invalid @enderror" type="file" name="backup" accept=".sql,application/sql,text/plain" required>
                                    @error('backup')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <label class="form-check mb-3">
                                    <input class="form-check-input @error('confirm_restore') is-invalid @enderror" type="checkbox" name="confirm_restore" value="1" required>
                                    <span class="form-check-label">Confirmo que deseo reemplazar los datos actuales.</span>
                                    @error('confirm_restore')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </label>
                                <button class="btn btn-danger w-100" type="submit">
                                    <i class="ti ti-database-import me-1"></i>
                                    Restaurar archivo SQL
                                </button>
                            </form>
                        </div>
                    </x-ui.card>
                </div>
            </div>
        </div>
    </div>
@endsection
