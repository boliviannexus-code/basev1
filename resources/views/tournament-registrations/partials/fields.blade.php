<div class="row g-3">
    @if (! ($registration ?? null))
        <div class="col-md-12">
            <label class="form-label">Equipo</label>
            @if ($selectedTeam ?? null)
                <input type="hidden" name="team_id" value="{{ $selectedTeam->id }}" data-registration-team-id>
                <input class="form-control" value="{{ $selectedTeam->name }}" disabled>
            @else
                <input class="form-control is-invalid" value="Selecciona un equipo desde el listado" disabled>
                <div class="invalid-feedback d-block">Selecciona un equipo desde el listado para iniciar la inscripcion.</div>
            @endif
            <div class="invalid-feedback" data-error-for="team_id"></div>
        </div>

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
            <label class="form-label" for="registration-category">Categoria</label>
            <select
                class="form-select"
                id="registration-category"
                name="category_id"
                data-tom-select
                data-registration-category
                data-registration-category-url-template="{{ route('tournament-registrations.tournaments.categories', ['tournament' => '__TOURNAMENT__']) }}"
                data-placeholder="Seleccionar categoria"
                required
            >
                <option value="">Seleccionar categoria</option>
            </select>
            <div class="invalid-feedback" data-error-for="category_id"></div>
            <div class="form-text" data-registration-category-help>Selecciona un torneo para cargar sus categorias habilitadas.</div>
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
        <div class="col-md-12">
            <label class="form-label" for="registration-category">Categoria</label>
            <select class="form-select" id="registration-category" name="category_id" required>
                @foreach ($registration->tournament?->categories ?? collect() as $category)
                    <option value="{{ $category->id }}" @selected((int) old('category_id', $registration->category_id) === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="category_id"></div>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label" for="registration-series">Serie</label>
        <select class="form-select" id="registration-series" name="series" required>
            @foreach (\App\Models\TournamentRegistration::SERIES as $value => $label)
                <option value="{{ $value }}" @selected(old('series', $registration->series ?? 'unica') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="invalid-feedback" data-error-for="series"></div>
    </div>

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
