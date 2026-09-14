<form method="POST" action="{{ route('extra-charges.assignments.update', $extraCharge) }}" data-ajax-form>@csrf @method('PUT')
<div class="alert alert-info py-2"><strong>{{ $extraCharge->name }}</strong> · Bs {{ number_format((float)$extraCharge->amount_per_team, 2, ',', '.') }} en {{ $extraCharge->installments }} cuotas</div>
@forelse($tournaments as $tournament)
@php($current = $extraCharge->assignments->where('tournament_id', $tournament->id))
<div class="card mb-3"><div class="card-header"><h4 class="card-title mb-0">{{ $tournament->name }}</h4></div><div class="card-body py-2">
<label class="form-check mb-2"><input class="form-check-input" name="assignments[{{ $tournament->id }}][all]" type="checkbox" value="1" @checked($current->contains('all_teams', true))><span class="form-check-label fw-semibold">Todos los equipos del torneo</span></label>
<div class="row g-2">@foreach($tournament->teams->unique('id') as $team)<div class="col-md-6"><label class="form-check"><input class="form-check-input" name="assignments[{{ $tournament->id }}][teams][]" type="checkbox" value="{{ $team->id }}" @checked($current->where('all_teams', false)->contains('team_id', $team->id))><span class="form-check-label">{{ $team->name }}</span></label></div>@endforeach</div>
@if($tournament->teams->isEmpty())<span class="text-body-secondary small">No hay equipos inscritos.</span>@endif
</div></div>
@empty <div class="alert alert-warning">No hay torneos registrados.</div> @endforelse
<div class="d-flex justify-content-end gap-2"><a class="btn btn-outline-secondary" href="{{ route('extra-charges.index') }}">Cancelar</a><button class="btn btn-primary">Guardar asignación</button></div>
</form>
