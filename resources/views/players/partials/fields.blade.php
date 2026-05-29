<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label" for="player-ci">CI / Carnet</label>
        <input class="form-control" id="player-ci" name="ci" value="{{ old('ci', $player->ci ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="ci"></div>
    </div>
    @if ($player?->exists)
        <div class="col-md-6">
            <label class="form-label" for="player-internal-code">Codigo interno</label>
            <input class="form-control" id="player-internal-code" value="{{ $player->internal_code ?? 'Pendiente' }}" readonly>
        </div>
    @endif
    <div class="col-md-6">
        <label class="form-label" for="player-first-name">Nombre</label>
        <input class="form-control" id="player-first-name" name="first_name" value="{{ old('first_name', $player->first_name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="first_name"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="player-last-name">Apellido</label>
        <input class="form-control" id="player-last-name" name="last_name" value="{{ old('last_name', $player->last_name ?? '') }}" required>
        <div class="invalid-feedback" data-error-for="last_name"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label" for="player-birth-date">Fecha de nacimiento</label>
        <input class="form-control" id="player-birth-date" name="birth_date" type="date" max="{{ now()->subDay()->toDateString() }}" value="{{ old('birth_date', isset($player) ? $player->birth_date?->format('Y-m-d') : '') }}" required>
        <div class="invalid-feedback" data-error-for="birth_date"></div>
    </div>
    <div class="col-md-12">
        <label class="form-label" for="player-notes">Notas</label>
        <textarea class="form-control" id="player-notes" name="notes" rows="3">{{ old('notes', $player->notes ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="notes"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="player-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $player->is_active ?? true))>
    <label class="form-check-label" for="player-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
