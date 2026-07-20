<form class="card mb-3" method="GET">
    <div class="card-body">
        <div class="row g-3">
            @php($includeDates = $includeDates ?? false)
            <div class="col-md-{{ $includeDates ? '3' : ($includeTeam ?? false ? '4' : '6') }}">
                <label class="form-label" for="report-tournament">Torneo</label>
                <select class="form-select" id="report-tournament" name="tournament_id" data-tom-select data-placeholder="Buscar torneo" required>
                    <option value="">Seleccionar torneo</option>
                    @foreach ($tournaments as $tournament)
                        <option value="{{ $tournament->id }}" @selected((int) request('tournament_id') === (int) $tournament->id)>{{ $tournament->name }}</option>
                    @endforeach
                </select>
            </div>
            @php($categoryRequired = $categoryRequired ?? false)
            <div class="col-md-{{ $includeDates ? '3' : ($includeTeam ?? false ? '4' : '6') }}">
                <label class="form-label" for="report-category">Categoria</label>
                <select class="form-select" id="report-category" name="category_id" data-tom-select data-placeholder="{{ $categoryRequired ? 'Seleccionar categoria' : 'Todas las categorias' }}" @required($categoryRequired)>
                    <option value="">{{ $categoryRequired ? 'Seleccionar categoria' : 'Todas las categorias' }}</option>
                    @foreach ($tournaments->flatMap->categories->unique('id')->sortBy('name') as $category)
                        <option value="{{ $category->id }}" @selected((int) request('category_id') === (int) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            @if ($includeTeam ?? false)
                <div class="col-md-{{ $includeDates ? '3' : '4' }}">
                    <label class="form-label" for="report-team">Equipo</label>
                    <select class="form-select" id="report-team" name="team_id" data-tom-select data-placeholder="Todos los equipos">
                        <option value="">Todos los equipos</option>
                        @foreach ($teams as $team)
                            <option value="{{ $team->id }}" @selected((int) request('team_id') === (int) $team->id)>{{ $team->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            @if ($includeDates)
                <div class="col-md-{{ $includeTeam ?? false ? '3' : '6' }}">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="report-date-from">Desde</label>
                            <input type="date" class="form-control" id="report-date-from" name="date_from" value="{{ request('date_from') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="report-date-to">Hasta</label>
                            <input type="date" class="form-control" id="report-date-to" name="date_to" value="{{ request('date_to') }}">
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @include('sports-reports.partials.filter-actions')
    </div>
</form>
