<form class="card mb-3" method="GET">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-{{ ($includeMatchday ?? false) ? '6' : '12' }}">
                <label class="form-label" for="report-season">Gestion</label>
                <select class="form-select" id="report-season" name="season_id" data-tom-select data-placeholder="Todas las gestiones">
                    <option value="">Todas las gestiones</option>
                    @foreach ($seasons as $season)
                        <option value="{{ $season->id }}" @selected((int) request('season_id') === (int) $season->id)>{{ $season->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($includeMatchday ?? false)
                <div class="col-md-6">
                    <label class="form-label" for="report-matchday">Jornada</label>
                    <select class="form-select" id="report-matchday" name="matchday_id" data-tom-select data-placeholder="Seleccionar jornada" required>
                        <option value="">Seleccionar jornada</option>
                        @foreach ($finalizedMatchdays->when(request('season_id'), fn ($rows, $seasonId) => $rows->where('season_id', (int) $seasonId)) as $matchday)
                            <option value="{{ $matchday->id }}" @selected((int) request('matchday_id') === (int) $matchday->id)>
                                {{ $matchday->name }} · {{ $matchday->season?->name ?? '-' }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        @include('sports-reports.partials.filter-actions')
    </div>
</form>
