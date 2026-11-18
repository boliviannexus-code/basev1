<div class="check-in-read-panel mb-3">
    <div class="check-in-read-header">
        <div class="d-flex flex-wrap align-items-center gap-3 gap-md-5">
            <h2 class="check-in-read-title fs-1 fw-bold text-break mb-0">{{ $record->guest_name }}</h2>
            <div class="fw-semibold text-break">{{ $resourceNames }}</div>
        </div>
        <span class="badge bg-{{ $statusTones[$record->status] ?? 'secondary' }}-lt check-in-read-status">{{ $statusLabels[$record->status] ?? $record->status }}</span>
    </div>

    <div @class(['check-in-read-stats', 'reservation-read-stats-with-actions' => filled($actionsView ?? null)])>
        <div>
            <div class="check-in-read-stat-line"><span>Ingreso</span><strong>{{ $record->check_in->format('d/m/Y') }}</strong></div>
            <div class="check-in-read-stat-line"><span>Salida</span><strong>{{ $record->check_out->format('d/m/Y') }}</strong></div>
        </div>
        <div>
            <div class="check-in-read-stat-line"><span>Espacios reservados</span><strong>{{ $resourceCount }}</strong></div>
            <div class="check-in-read-stat-line"><span>Personas grupo</span><strong>{{ $record->guests }}</strong></div>
        </div>
        <div class="check-in-read-balance {{ (float) $record->balance_amount > 0 ? 'is-pending' : '' }}">
            <span>Saldo reserva</span>
            <strong><x-ui.money :amount="$record->balance_amount" :currency="$record->currency" :exchange-rate="$currentExchangeRate?->rate" /></strong>
            <span @class(['text-danger' => (float) $record->balance_amount > 0])>{{ (float) $record->balance_amount > 0 ? 'Pendiente de pago' : 'Sin saldo pendiente' }}</span>
            @if ($paymentUrl ?? null)
                <a class="btn btn-success btn-sm mt-2" href="{{ $paymentUrl }}" data-modal-url="{{ $paymentUrl }}" data-modal-title="Registrar adelanto">
                    <i class="ti ti-cash-register me-1"></i>Registrar adelanto
                </a>
            @endif
        </div>
        @if ($actionsView ?? null)
            <div class="reservation-read-actions">
                <h3 class="h6 mb-2">Acciones de reserva</h3>
                @include($actionsView)
            </div>
        @endif
    </div>
</div>
