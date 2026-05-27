<div class="row g-3">
    @if (! ($registration ?? null))
        <div class="col-md-12">
            <label class="form-label" for="registration-tournament">Torneo</label>
            <select class="form-select" id="registration-tournament" name="tournament_id" data-tom-select data-registration-tournament data-placeholder="Seleccionar torneo" required>
                <option value="">Seleccionar torneo</option>
                @foreach ($tournaments as $tournament)
                    <option value="{{ $tournament->id }}" @selected((int) old('tournament_id') === $tournament->id)>
                        {{ $tournament->name }} - {{ $tournament->season?->name ?? '-' }}
                    </option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="tournament_id"></div>
        </div>

        <div class="col-md-12">
            <label class="form-label" for="registration-team">Equipo</label>
            <select class="form-select" id="registration-team" name="team_id" data-tom-select data-remote-team-select data-url="{{ route('tournament-registrations.teams.search') }}" data-placeholder="Buscar equipo" required>
                <option value="">Seleccionar equipo</option>
            </select>
            <div class="invalid-feedback" data-error-for="team_id"></div>
        </div>
    @else
        <div class="col-md-12">
            <label class="form-label">Torneo</label>
            <input class="form-control" value="{{ $registration->tournament?->name }}" disabled>
        </div>
        <div class="col-md-12">
            <label class="form-label">Equipo</label>
            <input class="form-control" value="{{ $registration->team?->name }}" disabled>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="registration-status">Estado</label>
        <select class="form-select" id="registration-status" name="status" required>
            @foreach (['registered', 'withdrawn'] as $status)
                <option value="{{ $status }}" @selected(old('status', $registration->status ?? 'registered') === $status)>{{ registration_status_label($status) }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="status"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="registration-notes">Notas</label>
        <textarea class="form-control" id="registration-notes" name="notes" rows="3">{{ old('notes', $registration->notes ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="notes"></div>
    </div>
</div>
