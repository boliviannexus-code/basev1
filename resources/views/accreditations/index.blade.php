@extends('layouts.admin')

@section('title', 'Acreditaciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Acreditaciones')
@section('page-subtitle', 'Delegados acreditados por equipo')

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" action="{{ route('accreditations.index') }}">
                <div class="col-lg-10">
                    <label class="form-label" for="accreditation-tournament">Torneo</label>
                    <select class="form-select" id="accreditation-tournament" name="tournament_id" onchange="this.form.submit()">
                        @foreach ($tournaments as $tournament)
                            <option value="{{ $tournament->id }}" @selected($selectedTournament?->is($tournament))>
                                {{ $tournament->name }} · {{ $tournament->season?->name ?? 'Sin gestion' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-2">
                    <button class="btn btn-primary w-100" type="submit">
                        <i class="ti ti-filter me-1"></i>
                        Ver equipos
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (! $selectedTournament)
        <div class="alert alert-info">No hay torneos registrados para acreditar delegados.</div>
    @else
        <x-ui.table-card title="Equipos inscritos">
            <x-slot:actions>
                <span class="badge text-bg-light border text-body">{{ $registrations->count() }} equipos</span>
            </x-slot:actions>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 6rem;">Nro.</th>
                            <th>Equipo</th>
                            <th>Categoria y serie</th>
                            <th>Delegado 1</th>
                            <th>Delegado 2</th>
                            <th>Delegado 3</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($registrations as $registration)
                            @php
                                $delegates = $registration->accreditations->keyBy('slot');
                            @endphp
                            <tr>
                                <td><span class="badge text-bg-light border text-body">{{ $registration->team_number ?? '-' }}</span></td>
                                <td class="fw-semibold">{{ $registration->team?->name ?? '-' }}</td>
                                <td>
                                    <div>{{ $registration->category?->name ?? '-' }}</div>
                                    <div class="text-body-secondary small">{{ $registration->seriesLabel() }}</div>
                                </td>
                                @for ($slot = 1; $slot <= 3; $slot++)
                                    @php $delegate = $delegates->get($slot); @endphp
                                    <td>
                                        @if ($delegate)
                                            <div class="fw-semibold">{{ $delegate->full_name }}</div>
                                            <div class="text-body-secondary small">
                                                CI {{ $delegate->ci }}
                                                @if ($delegate->player_id)
                                                    · Jugador
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-body-secondary">Sin acreditar</span>
                                        @endif
                                    </td>
                                @endfor
                                <td class="text-end">
                                    @can('accreditations.update')
                                        <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#accreditation-modal-{{ $registration->id }}">
                                            <i class="ti ti-id-badge-2 me-1"></i>
                                            Acreditar
                                        </button>
                                    @else
                                        <span class="text-body-secondary">Solo lectura</span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <x-ui.empty-row colspan="7" message="El torneo seleccionado no tiene equipos inscritos." />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-ui.table-card>

        @can('accreditations.update')
            @foreach ($registrations as $registration)
                @php
                    $delegates = $registration->accreditations->keyBy('slot');
                @endphp
                <div class="modal modal-blur fade" id="accreditation-modal-{{ $registration->id }}" tabindex="-1" aria-labelledby="accreditation-title-{{ $registration->id }}" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                        <form class="modal-content" method="POST" action="{{ route('accreditations.store', $registration) }}">
                            @csrf
                            <div class="modal-header">
                                <div>
                                    <h2 class="modal-title" id="accreditation-title-{{ $registration->id }}">Acreditaciones · {{ $registration->team?->name ?? '-' }}</h2>
                                    <div class="text-body-secondary small">{{ $selectedTournament->name }} · {{ $registration->category?->name ?? '-' }} · {{ $registration->seriesLabel() }}</div>
                                </div>
                                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row g-3">
                                    @for ($slot = 1; $slot <= 3; $slot++)
                                        @php $delegate = $delegates->get($slot); @endphp
                                        <div class="col-12" data-accreditation-delegate data-team-id="{{ $registration->team_id }}">
                                            <div class="border rounded p-3">
                                                <div class="d-flex align-items-center justify-content-between mb-3">
                                                    <div class="fw-semibold">Delegado {{ $slot }}</div>
                                                    <span class="small text-body-secondary" data-accreditation-status></span>
                                                </div>
                                                <div class="row g-3">
                                                    <div class="col-md-3">
                                                        <label class="form-label" for="delegate-{{ $registration->id }}-{{ $slot }}-ci">Carnet</label>
                                                        <input class="form-control" id="delegate-{{ $registration->id }}-{{ $slot }}-ci" name="delegates[{{ $slot }}][ci]" value="{{ old('delegates.'.$slot.'.ci', $delegate?->ci) }}" maxlength="50" data-accreditation-ci>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label" for="delegate-{{ $registration->id }}-{{ $slot }}-first-name">Nombre</label>
                                                        <input class="form-control" id="delegate-{{ $registration->id }}-{{ $slot }}-first-name" name="delegates[{{ $slot }}][first_name]" value="{{ old('delegates.'.$slot.'.first_name', $delegate?->first_name) }}" maxlength="255" data-accreditation-first-name>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label" for="delegate-{{ $registration->id }}-{{ $slot }}-last-name">Paterno</label>
                                                        <input class="form-control" id="delegate-{{ $registration->id }}-{{ $slot }}-last-name" name="delegates[{{ $slot }}][last_name]" value="{{ old('delegates.'.$slot.'.last_name', $delegate?->last_name) }}" maxlength="255" data-accreditation-last-name>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <label class="form-label" for="delegate-{{ $registration->id }}-{{ $slot }}-maternal-name">Materno</label>
                                                        <input class="form-control" id="delegate-{{ $registration->id }}-{{ $slot }}-maternal-name" name="delegates[{{ $slot }}][maternal_name]" value="{{ old('delegates.'.$slot.'.maternal_name', $delegate?->maternal_name) }}" maxlength="255" data-accreditation-maternal-name>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endfor
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                                <button class="btn btn-primary" type="submit">
                                    <i class="ti ti-device-floppy me-1"></i>
                                    Guardar acreditaciones
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endcan
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const lookupUrl = @json(route('accreditations.players.lookup'));

            document.querySelectorAll('[data-accreditation-ci]').forEach((input) => {
                input.addEventListener('blur', async () => {
                    const wrapper = input.closest('[data-accreditation-delegate]');
                    const ci = input.value.trim();
                    const status = wrapper?.querySelector('[data-accreditation-status]');

                    if (!wrapper || ci.length < 3) {
                        return;
                    }

                    if (status) {
                        status.textContent = 'Buscando...';
                    }

                    try {
                        const params = new URLSearchParams({
                            ci,
                            team_id: wrapper.dataset.teamId || '',
                        });
                        const response = await fetch(`${lookupUrl}?${params.toString()}`, {
                            headers: { Accept: 'application/json' },
                        });
                        const payload = await response.json();
                        const player = payload.players?.[0];

                        if (!player) {
                            if (status) {
                                status.textContent = 'Persona externa';
                            }

                            return;
                        }

                        wrapper.querySelector('[data-accreditation-first-name]').value = player.first_name || '';
                        wrapper.querySelector('[data-accreditation-last-name]').value = player.last_name || '';
                        wrapper.querySelector('[data-accreditation-maternal-name]').value = player.maternal_name || '';

                        if (status) {
                            status.textContent = player.is_team_player ? 'Jugador del equipo encontrado' : 'Jugador registrado encontrado';
                        }
                    } catch (error) {
                        if (status) {
                            status.textContent = 'No se pudo buscar';
                        }
                    }
                });
            });
        });
    </script>
@endpush
