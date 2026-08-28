@php
    $holderName = trim(($stay->holderGuest?->first_name ?? '').' '.($stay->holderGuest?->last_name ?? '')) ?: 'Sin titular';
    $resourceLabel = collect([
        $stay->space?->title ?: $stay->space?->name,
        $stay->room?->name ?: $stay->room?->title,
        $stay->bedUnit?->label,
    ])->filter()->implode(' / ');
    $money = fn ($value, $currency) => number_format((float) $value, 2).' '.$currency;
@endphp

<form method="POST" action="{{ route('occupancy.check-out.store', $stay) }}" data-check-out-form autocomplete="off" novalidate>
    @csrf

    <div class="occupancy-flow-panel">
        <div class="occupancy-flow-summary">
            <div>
                <span class="text-body-secondary">Check-in</span>
                <strong>{{ $group->code }}</strong>
            </div>
            <div>
                <span class="text-body-secondary">Titular</span>
                <strong>{{ $holderName }}</strong>
            </div>
            <div>
                <span class="text-body-secondary">Recurso</span>
                <strong>{{ $resourceLabel ?: 'Hospedaje' }}</strong>
            </div>
            <div>
                <span class="text-body-secondary">Salida programada</span>
                <strong>{{ $stay->check_out_date->format('d/m/Y') }}</strong>
            </div>
        </div>

        @if ($summary['can_check_out'])
            <div class="alert alert-success mb-0">
                <div class="fw-semibold">Sin deuda pendiente</div>
                <div>Se cerrara solo esta estancia. Las demas estancias del check-in seguiran activas.</div>
            </div>
        @else
            <div class="alert alert-warning mb-0">
                <div class="fw-semibold">Hay deuda pendiente</div>
                <div>Para realizar el check-out primero registra el cobro del saldo pendiente de esta estancia.</div>
            </div>

            <div class="table-responsive">
                <table class="table table-vcenter table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Estancia</th>
                            <th>Huesped</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summary['debts'] as $debt)
                            @php
                                $debtStay = $debt['stay'];
                                $debtHolder = trim(($debtStay->holderGuest?->first_name ?? '').' '.($debtStay->holderGuest?->last_name ?? '')) ?: 'Sin titular';
                            @endphp
                            <tr>
                                <td>#{{ $debtStay->id }}</td>
                                <td>{{ $debtHolder }}</td>
                                <td class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$debt['balance']" :currency="$debt['currency']" :exchange-rate="$debtStay->exchange_rate" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="invalid-feedback d-block" data-error-for="check_out"></div>

        <div class="d-flex justify-content-end gap-2">
            <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
            @if (! $summary['can_check_out'])
                <a
                    class="btn btn-success"
                    href="{{ route('stays.payments.create', ['stay' => $stay, 'scope' => 'stay']) }}"
                    data-modal-url="{{ route('stays.payments.create', ['stay' => $stay, 'scope' => 'stay']) }}"
                    data-modal-title="Cobrar estancia"
                >
                    <i class="ti ti-cash-register me-1"></i>Cobrar
                </a>
            @endif
            <button class="btn btn-warning" type="submit" @disabled(! $summary['can_check_out'])>
                <span class="spinner-border spinner-border-sm me-2 d-none" data-submit-spinner></span>
                <i class="ti ti-logout me-1"></i>Confirmar check-out
            </button>
        </div>
    </div>
</form>
