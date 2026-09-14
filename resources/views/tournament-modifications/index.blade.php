@extends('layouts.admin')

@section('title', 'Modificaciones de torneo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Modificaciones de torneo')
@section('page-subtitle', 'Operaciones excepcionales con trazabilidad completa')

@section('content')
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card h-100 border-primary">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar bg-primary-lt"><i class="ti ti-switch-horizontal fs-2"></i></span>
                        <div><h3 class="mb-0">Sustituir equipo</h3><div class="text-body-secondary small">Cesión de cupo deportivo</div></div>
                    </div>
                    <p>Reemplaza al titular de una inscripción conservando serie, número, fixture y resultados acumulados.</p>
                    <span class="badge text-bg-success">Disponible</span>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card h-100 bg-light-subtle">
                <div class="card-body text-body-secondary">
                    <span class="avatar bg-secondary-lt mb-3"><i class="ti ti-plus fs-2"></i></span>
                    <h3>Nuevas modificaciones</h3>
                    <p class="mb-0">Este módulo crecerá con nuevas operaciones excepcionales sin mezclar los flujos normales del torneo.</p>
                </div>
            </div>
        </div>
    </div>

    @can('tournament-modifications.create')
        <x-ui.table-card title="Aplicar sustitución" class="mt-3">
            <div class="alert alert-warning">
                Esta operación cambia el equipo en el fixture y transfiere al nuevo titular los resultados y ajustes del cupo. Las habilitaciones del plantel saliente serán desactivadas y sus acreditaciones de este torneo serán retiradas.
            </div>
            <form method="POST" action="{{ route('tournament-modifications.team-substitutions.store') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-6">
                        <label class="form-label" for="substitution-registration">Cupo y equipo saliente</label>
                        <select class="form-select @error('tournament_registration_id') is-invalid @enderror" id="substitution-registration" name="tournament_registration_id" required>
                            <option value="">Seleccionar inscripción</option>
                            @foreach ($registrations as $registration)
                                <option value="{{ $registration->id }}" @selected(old('tournament_registration_id') == $registration->id)>
                                    {{ $registration->tournament?->name }} · {{ $registration->category?->name }} · {{ $registration->seriesLabel() }} · N.º {{ $registration->team_number ?? '-' }} · {{ $registration->team?->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('tournament_registration_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-lg-6">
                        <label class="form-label" for="substitution-incoming-team">Equipo entrante</label>
                        <select class="form-select @error('incoming_team_id') is-invalid @enderror" id="substitution-incoming-team" name="incoming_team_id" required>
                            <option value="">Seleccionar equipo</option>
                            @foreach ($teams as $candidate)
                                <option value="{{ $candidate->id }}" @selected(old('incoming_team_id') == $candidate->id)>{{ $candidate->name }}</option>
                            @endforeach
                        </select>
                        @error('incoming_team_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="substitution-reason">Motivo y respaldo de la decisión</label>
                        <textarea class="form-control @error('reason') is-invalid @enderror" id="substitution-reason" name="reason" rows="4" minlength="10" maxlength="2000" required>{{ old('reason') }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input @error('confirm_substitution') is-invalid @enderror" type="checkbox" name="confirm_substitution" value="1" required>
                            <span class="form-check-label">Confirmo que el equipo entrante asumirá el cupo, calendario y resultados deportivos existentes.</span>
                        </label>
                        @error('confirm_substitution')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="text-end mt-3">
                    <button class="btn btn-danger" type="submit"><i class="ti ti-switch-horizontal me-1"></i>Aplicar sustitución</button>
                </div>
            </form>
        </x-ui.table-card>
    @endcan

    <x-ui.table-card title="Historial de sustituciones" class="mt-3">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th>Fecha</th><th>Torneo y cupo</th><th>Equipo saliente</th><th>Equipo entrante</th><th>Motivo</th><th>Impacto</th><th>Usuario</th></tr></thead>
            <tbody>
                @forelse ($substitutions as $substitution)
                    <tr>
                        <td>{{ $substitution->substituted_at?->format('d/m/Y H:i') }}</td>
                        <td><div class="fw-semibold">{{ $substitution->tournament?->name ?? '-' }}</div><div class="small text-body-secondary">{{ $substitution->registration?->category?->name ?? '-' }} · {{ $substitution->registration?->seriesLabel() ?? '-' }} · N.º {{ $substitution->registration?->team_number ?? '-' }}</div></td>
                        <td>{{ $substitution->outgoingTeam?->name ?? '-' }}</td>
                        <td class="fw-semibold text-success">{{ $substitution->incomingTeam?->name ?? '-' }}</td>
                        <td style="min-width: 14rem;">{{ $substitution->reason }}</td>
                        <td><div>{{ $substitution->fixture_matches_updated }} partido(s)</div><div class="small text-body-secondary">{{ $substitution->standing_adjustments_updated }} ajustes · {{ $substitution->habilitations_disabled }} habilitaciones · {{ $substitution->accreditations_removed }} acreditaciones</div></td>
                        <td>{{ $substitution->creator?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" message="Todavía no se realizaron sustituciones de equipos." />
                @endforelse
            </tbody>
        </table></div>
        <div class="mt-3">{{ $substitutions->links() }}</div>
    </x-ui.table-card>
@endsection
