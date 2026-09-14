<form method="POST" action="{{ route('matchday-court-fees.generate', $matchday) }}" data-ajax-form>
    @csrf
    <div class="alert alert-info py-2">
        <strong>{{ $matchday->name ?: 'Jornada '.$matchday->number }}</strong><br>
        Selecciona únicamente los cargos extra que deseas aplicar en esta jornada.
        Los cargos pendientes por cintillo, balón y tarjetas de jornadas anteriores se aplicarán automáticamente a los equipos programados.
    </div>

    @if ($charges->isNotEmpty())
        <div class="list-group list-group-flush border rounded">
            @foreach ($charges as $charge)
                <label class="list-group-item d-flex align-items-start gap-3">
                    <input class="form-check-input mt-1" name="extra_charge_ids[]" type="checkbox" value="{{ $charge->id }}">
                    <span class="flex-fill">
                        <span class="d-block fw-semibold">{{ $charge->name }}</span>
                        <span class="d-block text-body-secondary small">
                            Bs {{ number_format((float) $charge->amount_per_team, 2, ',', '.') }} por equipo ·
                            {{ $charge->installments }} cuota(s) ·
                            Bs {{ number_format((float) $charge->amount_per_team / $charge->installments, 2, ',', '.') }} por cuota
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        <div class="invalid-feedback d-block" data-error-for="extra_charge_ids"></div>
    @else
        <div class="alert alert-warning mb-0">No existen cargos extra activos con equipos asignados.</div>
    @endif

    <div class="d-flex justify-content-end gap-2 mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('matchday-court-fees.show', $matchday) }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">Confirmar y aplicar</button>
    </div>
</form>
