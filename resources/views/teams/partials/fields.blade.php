@php
    $canSelectCompany = \App\Support\CompanyContext::id(auth()->user()) === null;
    $selectedCompanyId = old('company_id', $team->company_id ?? '');
@endphp

<div class="row g-3">
    @if ($canSelectCompany)
        <div class="col-md-12">
            <label class="form-label" for="team-company">Liga deportiva</label>
            <select class="form-select" id="team-company" name="company_id" data-tom-select data-placeholder="Seleccionar liga deportiva" data-team-company required>
                <option value="">Seleccionar liga deportiva</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected((int) $selectedCompanyId === $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <div class="invalid-feedback" data-error-for="company_id"></div>
        </div>
    @endif

    <div class="col-md-12">
        <label class="form-label" for="team-name">Nombre del equipo</label>
        <input class="form-control" id="team-name" name="name" autocomplete="off" value="{{ old('name', $team->name ?? '') }}" data-team-name data-team-matches-url="{{ route('teams.matches') }}" data-team-ignore-id="{{ $team->id ?? '' }}" required>
        <div class="invalid-feedback" data-error-for="name"></div>
        <div class="border rounded mt-2 p-2 bg-body-tertiary small d-none" data-team-name-matches></div>
    </div>

    <div class="col-md-6">
        <label class="form-label" for="team-founded-at">Fecha de fundacion</label>
        <input class="form-control" id="team-founded-at" name="founded_at" type="date" max="{{ now()->toDateString() }}" value="{{ old('founded_at', isset($team) ? $team->founded_at?->format('Y-m-d') : now()->toDateString()) }}" required>
        <div class="invalid-feedback" data-error-for="founded_at"></div>
    </div>

    <div class="col-md-12">
        <label class="form-label" for="team-notes">Notas</label>
        <textarea class="form-control" id="team-notes" name="notes" rows="3">{{ old('notes', $team->notes ?? '') }}</textarea>
        <div class="invalid-feedback" data-error-for="notes"></div>
    </div>
</div>

<input type="hidden" name="is_active" value="0">
<div class="form-check form-switch mt-4">
    <input class="form-check-input" id="team-is-active" name="is_active" type="checkbox" value="1" @checked(old('is_active', $team->is_active ?? true))>
    <label class="form-check-label" for="team-is-active">Activo</label>
    <div class="invalid-feedback d-block" data-error-for="is_active"></div>
</div>
