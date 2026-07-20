@if (! empty($error))
    <div class="alert alert-warning mb-0">{{ $error }}</div>
@else
    <form method="POST" action="{{ route('player-transfers.store') }}" data-ajax-form novalidate>
        @csrf
        <input type="hidden" name="tournament_id" value="{{ $tournament->id }}">
        <input type="hidden" name="to_team_id" value="{{ $toTeam->id }}">
        <input type="hidden" name="player_id" value="{{ $player->id }}">

        <dl class="row mb-3">
            <dt class="col-sm-4">Jugador</dt>
            <dd class="col-sm-8">{{ $player->full_name }} · CI {{ $player->ci }}</dd>
            <dt class="col-sm-4">Division</dt>
            <dd class="col-sm-8">{{ $tournament->division?->name ?? '-' }}</dd>
            <dt class="col-sm-4">Equipo origen</dt>
            <dd class="col-sm-8">{{ $fromTeamPlayer->team?->name ?? '-' }}</dd>
            <dt class="col-sm-4">Equipo solicitante</dt>
            <dd class="col-sm-8">{{ $toTeam->name }}</dd>
            <dt class="col-sm-4">Precio del pase</dt>
            <dd class="col-sm-8">{{ money_format_decimal($setting->fee_amount) }}</dd>
        </dl>

        <div class="mb-3">
            <label class="form-label" for="transfer-requested-note">Nota de solicitud</label>
            <textarea class="form-control" id="transfer-requested-note" name="requested_note" rows="3" required></textarea>
            <div class="invalid-feedback" data-error-for="requested_note"></div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-primary" type="submit"><span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>Solicitar pase</button>
        </div>
    </form>
@endif
