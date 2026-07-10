@php
    $canSelectCompany = \App\Support\CompanyContext::id(auth()->user()) === null;
@endphp

<div class="row g-3">
    @php
        $selectedCategoryIds = collect(old('category_ids', ($tournament ?? null)?->categories?->pluck('id')->all() ?? (($tournament ?? null)?->category_id ? [$tournament->category_id] : [])))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

    <div class="col-md-12" data-tournament-stepper>
        <div class="d-flex align-items-center gap-2 mb-3">
            <span class="badge text-bg-primary" data-tournament-step-indicator="1">1</span>
            <span class="text-body-secondary">Datos del torneo</span>
            <span class="text-body-secondary">/</span>
            <span class="badge text-bg-secondary" data-tournament-step-indicator="2">2</span>
            <span class="text-body-secondary">Categorias</span>
        </div>

        <div data-tournament-step="1">
            <div class="row g-3">
                @if ($canSelectCompany && ! ($tournament ?? null))
                    <div class="col-md-12">
                        <label class="form-label" for="tournament-company">Liga deportiva</label>
                        <select class="form-select" id="tournament-company" name="company_id" data-tom-select data-placeholder="Seleccionar liga deportiva" required>
                            <option value="">Seleccionar liga deportiva</option>
                            @foreach ($companies as $company)
                                <option value="{{ $company->id }}" @selected((int) old('company_id', $tournament->company_id ?? 0) === $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-hint">Primero selecciona una gestion de esa misma liga.</div>
                        <div class="invalid-feedback" data-error-for="company_id"></div>
                    </div>
                @endif

                <div class="col-md-12">
                    <label class="form-label" for="tournament-season">Gestion</label>
                    <select class="form-select" id="tournament-season" name="season_id" data-tom-select data-placeholder="Seleccionar gestion" required>
                        <option value="" disabled @selected((int) old('season_id', $tournament->season_id ?? 0) === 0)>Seleccionar gestion</option>
                        @foreach ($seasons as $season)
                            <option value="{{ $season->id }}" data-label="{{ $season->name }}" @selected((int) old('season_id', $tournament->season_id ?? 0) === $season->id)>
                                {{ $season->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-error-for="season_id"></div>
                </div>

                <div class="col-md-12">
                    <label class="form-label" for="tournament-division">Division</label>
                    <select class="form-select" id="tournament-division" name="division_id" data-tom-select data-placeholder="Seleccionar division" data-tournament-division required>
                        <option value="" disabled @selected((int) old('division_id', $tournament->division_id ?? 0) === 0)>Seleccionar division</option>
                        @foreach ($divisions as $division)
                            <option value="{{ $division->id }}" data-label="{{ $division->name }}" @selected((int) old('division_id', $tournament->division_id ?? 0) === $division->id)>
                                {{ $division->name }} - {{ $division->min_age }} a {{ $division->max_age }} años
                            </option>
                        @endforeach
                    </select>
                    <div class="form-hint">La division define el rango de edad base para futuras habilitaciones.</div>
                    <div class="invalid-feedback" data-error-for="division_id"></div>
                </div>

                <div class="col-md-12">
                    <label class="form-label" for="tournament-name">Nombre del torneo</label>
                    <input class="form-control" id="tournament-name" name="name" value="{{ old('name', $tournament->name ?? '') }}" required>
                    <div class="invalid-feedback" data-error-for="name"></div>
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="tournament-status">Estado</label>
                    <select class="form-select" id="tournament-status" name="status" required>
                        @foreach (['planned', 'active', 'closed'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $tournament->status ?? 'planned') === $status)>{{ sports_status_label($status) }}</option>
                        @endforeach
                    </select>
                    <div class="invalid-feedback" data-error-for="status"></div>
                </div>
            </div>
        </div>

        <div class="d-none" data-tournament-step="2">
            <div class="row g-2" data-tournament-category-list>
                @foreach ($categories as $category)
                    <div class="col-md-6" data-tournament-category-option data-division-id="{{ $category->division_id }}">
                        <label class="form-check border rounded p-3 h-100">
                            <input class="form-check-input" type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategoryIds, true))>
                            <span class="form-check-label fw-semibold">{{ $category->name }}</span>
                        </label>
                    </div>
                @endforeach
            </div>
            <div class="text-body-secondary py-3 d-none" data-tournament-empty-categories>No hay categorias activas para la division seleccionada.</div>
            <div class="invalid-feedback d-block" data-error-for="category_ids"></div>
        </div>

        <div class="d-flex justify-content-between gap-2 mt-4">
            <button class="btn btn-outline-secondary d-none" type="button" data-tournament-prev-step>Anterior</button>
            <button class="btn btn-outline-primary ms-auto" type="button" data-tournament-next-step>Siguiente</button>
        </div>
    </div>
</div>
