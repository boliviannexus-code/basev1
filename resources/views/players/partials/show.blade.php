@php
    $photoDataUri = $photoDataUri ?? null;
    $qrCodeDataUri = $qrCodeDataUri ?? null;
    $rightIndexFingerprint = $rightIndexFingerprint ?? null;
    $initials = str(mb_substr((string) $player->first_name, 0, 1).mb_substr((string) $player->last_name, 0, 1))->upper()->toString();
@endphp

<div class="vstack gap-3">
    <section class="border rounded bg-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <h3 class="h5 mb-1">{{ $player->full_name }}</h3>
                <div class="text-body-secondary small">CI {{ $player->ci }} · {{ $player->internal_code ?: 'Sin codigo' }}</div>
            </div>
            <span class="badge text-bg-{{ $player->is_active ? 'success' : 'secondary' }}">{{ $player->is_active ? 'Activo' : 'Inactivo' }}</span>
        </div>

        <div class="row g-2 mt-2">
            <div class="col-sm-4">
                <div class="border rounded p-2 h-100">
                    <div class="text-body-secondary small">Nacimiento</div>
                    <div class="fw-semibold">{{ $player->birth_date?->format('Y-m-d') ?? '-' }}</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="border rounded p-2 h-100">
                    <div class="text-body-secondary small">Edad</div>
                    <div class="fw-semibold">{{ $player->age() ?? '-' }} anos</div>
                </div>
            </div>
            <div class="col-sm-4">
                <div class="border rounded p-2 h-100">
                    <div class="text-body-secondary small">Equipos registrados</div>
                    <div class="fw-semibold">{{ $player->teamPlayers->count() }}</div>
                </div>
            </div>
            <div class="col-12">
                <div class="border rounded p-2">
                    <div class="text-body-secondary small">Notas</div>
                    <div>{{ $player->notes ?: '-' }}</div>
                </div>
            </div>
        </div>
    </section>

    <section class="row g-3 align-items-stretch">
        <div class="col-md-4">
            <div class="border rounded bg-body h-100 p-3 text-center">
                <div class="text-body-secondary small mb-2">Codigo QR</div>
                @if ($qrCodeDataUri)
                    <img class="mx-auto" src="{{ $qrCodeDataUri }}" alt="QR {{ $player->internal_code }}" width="132" height="132">
                    <div class="fw-semibold small mt-2">{{ $player->internal_code }}</div>
                @else
                    <div class="border rounded bg-body-tertiary d-flex align-items-center justify-content-center mx-auto" style="width: 132px; height: 132px;">
                        <span class="text-body-secondary small">Sin QR</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-4" data-player-photo-panel>
            <div class="border rounded bg-body h-100 p-3 text-center">
                <div class="text-body-secondary small mb-2">Fotografia</div>
                @if ($photoDataUri)
                    <img class="rounded border bg-white object-fit-cover mx-auto" src="{{ $photoDataUri }}" alt="Foto de {{ $player->full_name }}" width="150" height="150" data-player-photo-preview>
                @else
                    <div class="rounded border bg-white d-flex align-items-center justify-content-center mx-auto" style="width: 150px; height: 150px;" data-player-photo-placeholder>
                        <span class="fw-semibold text-body-secondary fs-3">{{ $initials }}</span>
                    </div>
                    <div class="text-body-secondary small mt-2">Foto pendiente</div>
                @endif

                @can('players.update')
                    <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="{{ route('players.photo.edit', $player) }}" data-modal-url="{{ route('players.photo.edit', $player) }}" data-modal-title="{{ $player->photo_path ? 'Actualizar fotografia' : 'Guardar fotografia' }}">
                        {{ $player->photo_path ? 'Actualizar foto' : 'Guardar foto' }}
                    </a>
                @endcan
            </div>
        </div>

        <div class="col-md-4">
            <div class="border rounded bg-body h-100 p-3 text-center">
                <div class="text-body-secondary small mb-2">Biometrico</div>
                <div class="border rounded bg-body-tertiary d-flex align-items-center justify-content-center mx-auto" style="width: 132px; height: 132px;">
                    <i class="ti ti-fingerprint fs-1 text-{{ $rightIndexFingerprint ? 'success' : 'secondary' }}"></i>
                </div>
                @if ($rightIndexFingerprint)
                    <div class="fw-semibold small mt-2">Indice derecho registrado</div>
                    <div class="text-body-secondary small">{{ $rightIndexFingerprint->enrolled_at?->format('Y-m-d H:i') ?? '-' }}</div>
                @else
                    <div class="fw-semibold small mt-2">Sin huella vinculada</div>
                    <div class="text-body-secondary small">Preparado para control biometrico</div>
                @endif

                @can('players.update')
                    <a class="btn btn-outline-primary btn-sm w-100 mt-3" href="{{ route('players.biometric-registration.create', $player) }}" data-modal-url="{{ route('players.biometric-registration.create', $player) }}" data-modal-title="Registro biometrico">
                        Registrar huella
                    </a>
                @endcan
            </div>
        </div>
    </section>

    <section class="border rounded bg-body p-3">
        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
            <h4 class="h6 mb-0">Historial del jugador</h4>
            <span class="badge text-bg-secondary">{{ $player->teamPlayers->count() }}</span>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Equipo</th>
                        <th>Division</th>
                        <th>Estado</th>
                        <th>Ingreso</th>
                        <th>Salida</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($player->teamPlayers as $teamPlayer)
                        <tr>
                            <td>{{ $teamPlayer->team?->name ?? '-' }}</td>
                            <td>{{ $teamPlayer->division?->name ?? '-' }}</td>
                            <td><span class="badge text-bg-{{ $teamPlayer->status === 'active' ? 'success' : 'secondary' }}">{{ $teamPlayer->status === 'active' ? 'Activo' : 'Inactivo' }}</span></td>
                            <td>{{ $teamPlayer->joined_at?->format('Y-m-d') ?? '-' }}</td>
                            <td>{{ $teamPlayer->ended_at?->format('Y-m-d') ?? '-' }}</td>
                        </tr>
                    @empty
                        <x-ui.empty-row colspan="5" message="Sin historial de equipos." />
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
