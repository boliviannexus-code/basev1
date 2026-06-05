<form
    method="POST"
    action="{{ route('players.biometric-registration.store', $player) }}"
    data-player-biometric-registration
    data-show-url="{{ route('players.show', $player) }}"
    novalidate
>
    @csrf

    <div class="vstack gap-3">
        <section class="border rounded bg-body p-3">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-body-secondary small">Jugador</div>
                    <div class="fw-semibold">{{ $player->full_name }}</div>
                    <div class="text-body-secondary small">CI {{ $player->ci }} · {{ $player->internal_code ?: 'Sin codigo' }}</div>
                </div>
                <span class="badge text-bg-primary">Indice derecho</span>
            </div>
        </section>

        @if ($activeFingerprint)
            <div class="alert alert-warning mb-0">
                Este jugador ya tiene registrado el indice derecho desde {{ $activeFingerprint->enrolled_at?->format('Y-m-d H:i') ?? 'fecha no disponible' }}.
            </div>
        @endif

        <section class="border rounded bg-body p-3">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Dedo solicitado</label>
                    <input class="form-control" value="Indice derecho" disabled>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Formato</label>
                    <input class="form-control" value="PNG base64" disabled>
                </div>

                <div class="col-12">
                    <div class="d-flex flex-wrap gap-2">
                        <button class="btn btn-outline-info btn-sm" type="button" data-player-biometric-detect @disabled($activeFingerprint)>Detectar lector</button>
                        <button class="btn btn-outline-primary btn-sm" type="button" data-player-biometric-capture @disabled($activeFingerprint)>Capturar huella</button>
                        <button class="btn btn-primary btn-sm" type="submit" data-player-biometric-save disabled>Guardar</button>
                    </div>
                    <div class="form-hint mt-2" data-player-biometric-status>
                        {{ $activeFingerprint ? 'Registro bloqueado: el indice derecho ya esta registrado.' : 'Listo para detectar lector.' }}
                    </div>
                    <div class="invalid-feedback d-block" data-error-for="sample_image"></div>
                </div>

                <div class="col-12">
                    <div class="border rounded p-3 text-center bg-light">
                        <img class="img-fluid d-none" alt="Huella capturada" data-player-biometric-preview style="max-height: 260px;">
                        <div class="text-body-secondary" data-player-biometric-empty>Sin huella capturada.</div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</form>
