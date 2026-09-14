<form method="POST" action="{{ route('court-fees.tournaments.update', $courtFee) }}" data-ajax-form>
    @csrf
    @method('PUT')
    <div class="alert alert-info py-2 mb-3"><span class="text-body-secondary">Derecho de cancha:</span> <strong>{{ $courtFee->name }}</strong></div>
    @php($selected = old('tournament_ids', $courtFee->tournaments->modelKeys()))
    <label class="form-label">Selecciona los torneos</label>
    @if ($tournaments->isNotEmpty())
        <div class="list-group list-group-flush border rounded" style="max-height: 22rem; overflow-y: auto;">
            @foreach ($tournaments as $tournament)
                <label class="list-group-item d-flex align-items-center gap-3 cursor-pointer">
                    <input class="form-check-input m-0" name="tournament_ids[]" type="checkbox" value="{{ $tournament->id }}" @checked(in_array($tournament->id, $selected))>
                    <span class="flex-fill">
                        <span class="d-block fw-semibold">{{ $tournament->name }}</span>
                        <span class="d-block text-body-secondary small">
                            {{ $tournament->season?->name ?? 'Sin gestión' }} · {{ ucfirst($tournament->status) }}
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        <div class="form-hint">Marca uno o varios torneos para vincularlos con este derecho de cancha.</div>
    @endif
    <div class="invalid-feedback d-block" data-error-for="tournament_ids"></div>
    @if ($tournaments->isEmpty())<div class="alert alert-info mt-3 mb-0">No existen torneos disponibles en esta liga.</div>@endif
    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('court-fees.show', $courtFee) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Guardar vínculos</button>
    </div>
</form>
