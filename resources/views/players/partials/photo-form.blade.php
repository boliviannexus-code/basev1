@php
    $photoDataUri = $photoDataUri ?? null;
    $initials = str(mb_substr((string) $player->first_name, 0, 1).mb_substr((string) $player->last_name, 0, 1))->upper()->toString();
@endphp

<div class="vstack gap-3">
    <section class="border rounded bg-body p-3">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div>
                <div class="text-body-secondary small">Jugador</div>
                <div class="fw-semibold">{{ $player->full_name }}</div>
                <div class="text-body-secondary small">CI {{ $player->ci }} · {{ $player->internal_code ?: 'Sin codigo' }}</div>
            </div>
            <span class="badge text-bg-primary">Fotografia</span>
        </div>
    </section>

    <section class="border rounded bg-body p-3">
        <form
            method="POST"
            action="{{ route('players.photo.update', $player) }}"
            enctype="multipart/form-data"
            data-ajax-form
            data-keep-modal="true"
            data-show-url="{{ route('players.show', $player) }}"
            data-player-photo-form
            novalidate
        >
            @csrf

            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border rounded bg-body-tertiary d-flex align-items-center justify-content-center mx-auto overflow-hidden position-relative" style="width: min(100%, 320px); aspect-ratio: 1 / 1; touch-action: none; cursor: move;" data-player-photo-cropper>
                        <canvas class="w-100 h-100 d-none" width="600" height="600" data-player-photo-canvas></canvas>
                        @if ($photoDataUri)
                            <img class="object-fit-cover w-100 h-100" src="{{ $photoDataUri }}" alt="Foto de {{ $player->full_name }}" data-player-photo-preview>
                        @else
                            <div class="d-flex align-items-center justify-content-center w-100 h-100" data-player-photo-placeholder>
                                <span class="fw-semibold text-body-secondary fs-1">{{ $initials }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="player-photo-modal-{{ $player->id }}">Archivo de foto</label>
                    <input class="form-control" id="player-photo-modal-{{ $player->id }}" name="photo" type="file" accept="image/jpeg,image/png,image/webp" capture="environment" data-player-photo-input required>
                    <div class="form-hint mt-2" data-player-photo-meta>Salida: 600x600 WebP, 40-120 KB aprox.</div>
                    <div class="invalid-feedback d-block" data-error-for="photo"></div>

                    <div class="mt-3">
                        <label class="form-label" for="player-photo-zoom-{{ $player->id }}">Zoom</label>
                        <input class="form-range" id="player-photo-zoom-{{ $player->id }}" type="range" min="1" max="3" step="0.01" value="1" data-player-photo-zoom disabled>
                    </div>

                    <button class="btn btn-primary btn-sm mt-3" type="submit">
                        <span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>{{ $player->photo_path ? 'Actualizar foto' : 'Guardar foto' }}
                    </button>
                </div>
            </div>
        </form>
    </section>

    @if ($player->photo_path)
        <section class="border rounded bg-body p-3">
            <form method="POST" action="{{ route('players.photo.destroy', $player) }}" data-ajax-form data-show-url="{{ route('players.show', $player) }}" data-confirm-delete="Eliminar fotografia del jugador?">
                @csrf
                @method('DELETE')
                <button class="btn btn-outline-danger btn-sm" type="submit">Eliminar foto</button>
            </form>
        </section>
    @endif
</div>
