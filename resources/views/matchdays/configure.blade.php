@extends('layouts.admin')

@section('title', 'Configurar jornada | '.config('app.name', 'Base Admin'))
@section('page-title', $matchday->name ?? 'Jornada '.$matchday->number)
@section('page-subtitle', $season->name.' · '.($season->company?->name ?? '-'))

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('matchdays.show', $season) }}">
            <i class="ti ti-arrow-left me-1"></i>
            Jornadas
        </a>
        <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-{{ $matchday->status === 'finalized' ? 'success' : 'secondary' }}">
                {{ $matchday->status === 'finalized' ? 'Finalizada' : 'Borrador' }}
            </span>
            @if ($matchday->status === 'finalized')
                <a class="btn btn-outline-primary btn-sm" href="{{ route('matchdays.print', $matchday) }}" target="_blank" rel="noopener">
                    <i class="ti ti-printer me-1"></i>
                    Imprimir
                </a>
            @else
                <a class="btn btn-outline-primary btn-sm" href="{{ route('matchdays.preview', $matchday) }}">
                    <i class="ti ti-eye me-1"></i>
                    Vista previa
                </a>
            @endif
            @can('matchdays.update')
                @if ($matchday->status !== 'finalized')
                    <form method="POST" action="{{ route('matchdays.finish', $matchday) }}" data-confirm-delete="Finalizar jornada?" data-confirm-button-text="Si, finalizar" data-confirm-text="Estas seguro? Se validara que todas las fechas tengan partidos y fiscalias asignadas. Luego ya no se podran modificar fechas ni partidos programados." data-confirm-color="#198754">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-success btn-sm" type="submit">
                            <i class="ti ti-check me-1"></i>
                            Finalizar
                        </button>
                    </form>
                @endif
            @endcan
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.table-card title="Agregar fechas">
                @if ($matchday->status === 'finalized')
                    <div class="alert alert-success mb-0">La jornada esta finalizada. No se pueden agregar fechas.</div>
                @else
                @can('matchdays.update')
                    <form method="POST" action="{{ route('matchdays.dates.store', $matchday) }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="matchday-court">Cancha</label>
                            <select class="form-select @error('court_id') is-invalid @enderror" id="matchday-court" name="court_id" required>
                                <option value="">Seleccionar cancha</option>
                                @foreach ($courts as $court)
                                    <option value="{{ $court->id }}" @selected((int) old('court_id') === (int) $court->id)>
                                        {{ $court->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('court_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if ($courts->isEmpty())
                                <div class="form-hint text-danger">
                                    Registra una cancha activa antes de agregar fechas.
                                    @can('courts.create')
                                        <a href="{{ route('courts.create') }}">Crear cancha</a>
                                    @endcan
                                </div>
                            @endif
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="matchday-date-input">Selecciona una fecha</label>
                            <div class="input-group">
                                <input class="form-control @error('date') is-invalid @enderror" id="matchday-date-input" name="date" type="date" value="{{ old('date') }}" required>
                                <button class="btn btn-outline-primary" type="submit" @disabled($courts->isEmpty())>
                                    <i class="ti ti-plus me-1"></i>
                                    Agregar
                                </button>
                                @error('date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </form>
                @else
                    <div class="alert alert-info mb-0">No tienes permiso para agregar fechas a esta jornada.</div>
                @endcan
                @endif
            </x-ui.table-card>
        </div>

        <div class="col-lg-7">
            <x-ui.table-card title="Fechas de la jornada">
                @error('finalizar')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                @error('partidos')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                @error('fiscales')
                    <div class="alert alert-danger">{{ $message }}</div>
                @enderror
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th class="text-center" style="width: 6.5rem;">Orden</th>
                            <th style="width: 7.5rem;">Fecha</th>
                            <th style="width: 6rem;">Dia</th>
                            <th style="width: 7.5rem;">Cancha</th>
                            <th class="text-center" style="width: 6rem;">Partidos</th>
                            <th class="text-center" style="width: 6rem;">Fiscales</th>
                            <th class="text-end" style="width: 12rem;">Accion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dates as $date)
                            <tr>
                                <td class="text-center">
                                    @if ($matchday->status === 'finalized')
                                        <span class="badge text-bg-light border text-body">{{ $loop->iteration }}</span>
                                    @else
                                        @can('matchdays.update')
                                            <div class="d-inline-flex gap-1">
                                                <form method="POST" action="{{ route('matchdays.dates.reorder', $matchday) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="date_id" value="{{ $date->id }}">
                                                    <input type="hidden" name="direction" value="up">
                                                    <button class="btn btn-outline-secondary btn-icon btn-sm" type="submit" title="Subir fecha" aria-label="Subir fecha" @disabled($loop->first)>
                                                        <i class="ti ti-arrow-up"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('matchdays.dates.reorder', $matchday) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="date_id" value="{{ $date->id }}">
                                                    <input type="hidden" name="direction" value="down">
                                                    <button class="btn btn-outline-secondary btn-icon btn-sm" type="submit" title="Bajar fecha" aria-label="Bajar fecha" @disabled($loop->last)>
                                                        <i class="ti ti-arrow-down"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        @else
                                            <span class="badge text-bg-light border text-body">{{ $loop->iteration }}</span>
                                        @endcan
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $date->date->format('d/m/Y') }}</span>
                                </td>
                                <td>{{ ucfirst($date->date->copy()->locale('es')->translatedFormat('l')) }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $date->court?->name ?? 'Sin cancha' }}</div>
                                    @if ($date->court?->address)
                                        <div class="text-body-secondary small">{{ $date->court->address }}</div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-body">{{ $date->fixtureMatches->count() }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge text-bg-light border text-body">{{ $date->fiscals->count() }}</span>
                                </td>
                                <td class="text-end">
                                    @if ($matchday->status === 'finalized')
                                        <span class="text-body-secondary small">Finalizada</span>
                                    @else
                                        @can('matchdays.update')
                                            <button class="btn btn-outline-primary btn-icon btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#matchday-date-edit-{{ $date->id }}" title="Modificar fecha y cancha" aria-label="Modificar fecha y cancha">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                        @endcan
                                        <a class="btn btn-outline-primary btn-sm" href="{{ route('matchdays.dates.configure', $date) }}">
                                            Configurar fecha
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="7" message="Aun no hay fechas configuradas en esta jornada." />
                        @endforelse
                    </tbody>
                </table>

                @if ($matchday->status !== 'finalized')
                    @can('matchdays.update')
                        @foreach ($dates as $date)
                            <div class="modal modal-blur fade" id="matchday-date-edit-{{ $date->id }}" tabindex="-1" aria-labelledby="matchday-date-edit-title-{{ $date->id }}" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <form class="modal-content" method="POST" action="{{ route('matchdays.dates.update', $date) }}">
                                        @csrf
                                        @method('PATCH')
                                        <div class="modal-header">
                                            <h2 class="modal-title" id="matchday-date-edit-title-{{ $date->id }}">Modificar fecha</h2>
                                            <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label" for="matchday-date-value-{{ $date->id }}">Fecha</label>
                                                <input class="form-control @error('date') is-invalid @enderror" id="matchday-date-value-{{ $date->id }}" name="date" type="date" value="{{ old('date', $date->date->format('Y-m-d')) }}" required>
                                                @error('date')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                            <div class="mb-0">
                                                <label class="form-label" for="matchday-date-court-{{ $date->id }}">Cancha</label>
                                                <select class="form-select @error('court_id') is-invalid @enderror" id="matchday-date-court-{{ $date->id }}" name="court_id" required>
                                                    @foreach ($courts as $court)
                                                        <option value="{{ $court->id }}" @selected((int) old('court_id', $date->court_id) === (int) $court->id)>
                                                            {{ $court->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('court_id')
                                                    <div class="invalid-feedback">{{ $message }}</div>
                                                @enderror
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                            <button class="btn btn-primary" type="submit" @disabled($courts->isEmpty())>
                                                <i class="ti ti-device-floppy me-1"></i>
                                                Guardar
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    @endcan
                @endif
            </x-ui.table-card>
        </div>
    </div>
@endsection
