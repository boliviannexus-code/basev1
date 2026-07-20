@php
    $refreshUrl = $tournamentId && $teamId
        ? route('player-habilitations.show', ['tournament' => $tournamentId, 'team' => $teamId])
        : route('player-habilitations.index');
@endphp

<form method="POST" action="{{ route('player-habilitations.affiliate') }}" data-ajax-form data-affiliate-player-form data-player-lookup-url="{{ route('player-habilitations.player-lookup') }}" data-player-age-url="{{ route('player-habilitations.player-age') }}" data-refresh-url="{{ $refreshUrl }}" novalidate>
    @csrf
    <input type="hidden" name="tournament_id" value="{{ $tournamentId }}">
    <input type="hidden" name="team_id" value="{{ $teamId }}">

    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label" for="affiliate-ci">CI / Carnet</label>
            <input class="form-control" id="affiliate-ci" name="ci" value="{{ $ci ?? '' }}" data-affiliate-ci required>
            <div class="form-hint">Si el CI ya existe, se reutilizara el jugador registrado.</div>
            <div class="invalid-feedback" data-error-for="ci"></div>
        </div>
        <div class="col-md-12 d-none" data-affiliate-player-summary></div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-first-name">Nombre</label>
            <input class="form-control" id="affiliate-first-name" name="first_name" data-affiliate-player-field>
            <div class="invalid-feedback" data-error-for="first_name"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-last-name">Paterno</label>
            <input class="form-control" id="affiliate-last-name" name="last_name" data-affiliate-player-field>
            <div class="invalid-feedback" data-error-for="last_name"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-maternal-name">Materno</label>
            <input class="form-control" id="affiliate-maternal-name" name="maternal_name" data-affiliate-player-field>
            <div class="form-hint">Se requiere al menos un apellido: paterno o materno.</div>
            <div class="invalid-feedback" data-error-for="maternal_name"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-birth-date">Fecha de nacimiento</label>
            <input class="form-control" id="affiliate-birth-date" name="birth_date" type="date" max="{{ now()->subDay()->toDateString() }}" data-affiliate-player-field>
            <div class="invalid-feedback" data-error-for="birth_date"></div>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-age">Edad</label>
            <input class="form-control" id="affiliate-age" data-affiliate-age value="-" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-internal-code">Codigo interno</label>
            <input class="form-control" id="affiliate-internal-code" data-affiliate-internal-code value="Se generara automaticamente" readonly>
        </div>
        <div class="col-md-6">
            <label class="form-label" for="affiliate-joined-at">Fecha de afiliacion</label>
            <input class="form-control" id="affiliate-joined-at" name="joined_at" type="date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}">
            <div class="invalid-feedback" data-error-for="joined_at"></div>
        </div>
        <div class="col-md-12">
            <label class="form-label" for="affiliate-notes">Notas</label>
            <textarea class="form-control" id="affiliate-notes" name="notes" rows="3"></textarea>
            <div class="invalid-feedback" data-error-for="notes"></div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-end gap-2">
        <button class="btn btn-outline-warning d-none" type="button" data-transfer-request-button disabled>Solicitar pase</button>
        <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Afiliar jugador</button>
    </div>
</form>
