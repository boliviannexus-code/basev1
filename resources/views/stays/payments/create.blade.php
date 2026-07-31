@php
    $money = fn ($value, $currency = 'BOB') => number_format((float) $value, 2).' '.$currency;
    $currency = 'BOB';
    $exchangeRate = (float) ($exchangeRate ?? 0);
    $isMultipleStay = (bool) ($isMultipleStay ?? false);
@endphp

<form
    method="POST"
    action="{{ route('stays.payments.store', $stay) }}"
    autocomplete="off"
    novalidate
    data-ajax-form
    data-requires-transaction-pin
    data-stay-payment-form
    data-can-check-out-today="{{ $canCheckOutToday ? '1' : '0' }}"
    data-can-submit-payment="{{ $paymentMethods->isNotEmpty() ? '1' : '0' }}"
    data-checkout-balance="{{ number_format((float) $stayBalance, 2, '.', '') }}"
>
    @csrf

    <div class="alert alert-info">
        El cobro se consolidara en la caja abierta del usuario dueño del codigo de caja.
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="stay-payment-scope">Cobrar</label>
            <select class="form-select @error('scope', 'stayPayment') is-invalid @enderror" id="stay-payment-scope" name="scope">
                <option value="stay" data-balance="{{ number_format((float) $stayBalance, 2, '.', '') }}" @selected(old('scope', $scope) === 'stay')>Estancia actual</option>
                @if ($isMultipleStay)
                    <option value="group" data-balance="{{ number_format((float) $groupBalance, 2, '.', '') }}" @selected(old('scope', $scope) === 'group')>Todo el grupo</option>
                @endif
            </select>
            @error('scope', 'stayPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">Saldo disponible</label>
            <div class="form-control-plaintext fw-semibold" data-stay-payment-balance-label>{{ $money($balance, $currency) }}</div>
            @if ($isMultipleStay)
                <div class="form-hint vstack gap-1 mt-1">
                    <div class="d-flex justify-content-between gap-3">
                        <span>Deuda estancia actual</span>
                        <strong>{{ $money($stayBalance, $currency) }}</strong>
                    </div>
                    <div class="d-flex justify-content-between gap-3">
                        <span>Deuda todo el grupo</span>
                        <strong>{{ $money($groupBalance, $currency) }}</strong>
                    </div>
                </div>
            @endif
        </div>

        <div class="col-md-6">
            <label class="form-label" for="stay-payment-method">Metodo de pago</label>
            <select class="form-select @error('payment_method_id', 'stayPayment') is-invalid @enderror" id="stay-payment-method" name="payment_method_id" required>
                <option value="">Seleccionar</option>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                @endforeach
            </select>
            @error('payment_method_id', 'stayPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="stay-payment-amount">Monto BOB</label>
            <input class="form-control text-end @error('amount', 'stayPayment') is-invalid @enderror" id="stay-payment-amount" name="amount" type="number" min="0.01" step="0.01" max="{{ number_format((float) $balance, 2, '.', '') }}" value="{{ old('amount', number_format((float) $balance, 2, '.', '')) }}" data-stay-payment-amount data-payment-bob-amount data-payment-exchange-rate="{{ $exchangeRate }}" required>
            <div class="form-hint" data-payment-usd-reference>
                @if ($exchangeRate > 0)
                    Referencia USD: {{ $money((float) old('amount', $balance) / $exchangeRate, 'USD') }}
                @else
                    Sin tipo de cambio vigente para referencia USD.
                @endif
            </div>
            @error('amount', 'stayPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label class="form-label" for="stay-payment-reference">Referencia</label>
            <input class="form-control @error('reference', 'stayPayment') is-invalid @enderror" id="stay-payment-reference" name="reference" value="{{ old('reference') }}" placeholder="Comprobante externo, QR, transferencia, nota">
            @error('reference', 'stayPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    @error('action', 'stayPayment')<div class="invalid-feedback d-block mt-3">{{ $message }}</div>@enderror

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-outline-success" type="submit" name="action" value="collect" @disabled($paymentMethods->isEmpty() || (float) $balance <= 0)>
            <i class="ti ti-cash-register me-1"></i>Registrar cobro
        </button>
        <button class="btn btn-success" type="submit" name="action" value="collect_checkout" data-stay-payment-checkout-button @disabled($paymentMethods->isEmpty() || ! $canCheckOutToday || (float) $stayBalance <= 0)>
            <i class="ti ti-logout me-1"></i>Cobrar y check-out
        </button>
    </div>
</form>
