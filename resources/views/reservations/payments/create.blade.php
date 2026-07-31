@php
    $money = fn ($value, $targetCurrency = 'BOB') => number_format((float) $value, 2).' '.$targetCurrency;
    $exchangeRate = (float) ($exchangeRate ?? 0);
@endphp

<form
    method="POST"
    action="{{ route('admin.reservation-groups.payments.store', $group) }}"
    data-ajax-form
    data-requires-transaction-pin
    autocomplete="off"
    novalidate
>
    @csrf

    <div class="alert alert-info">
        El adelanto se consolidara en la caja abierta del usuario dueño del codigo de caja.
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Saldo disponible</label>
            <div class="form-control-plaintext fw-semibold">{{ $money($balance, $currency) }}</div>
        </div>

        <div class="col-md-6">
            <label class="form-label" for="reservation-payment-method">Metodo de pago</label>
            <select class="form-select @error('payment_method_id', 'reservationPayment') is-invalid @enderror" id="reservation-payment-method" name="payment_method_id" required>
                <option value="">Seleccionar</option>
                @foreach ($paymentMethods as $method)
                    <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>{{ $method->name }}</option>
                @endforeach
            </select>
            @error('payment_method_id', 'reservationPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="reservation-payment-amount">Monto BOB</label>
            <input class="form-control text-end @error('amount', 'reservationPayment') is-invalid @enderror" id="reservation-payment-amount" name="amount" type="number" min="0.01" step="0.01" max="{{ number_format((float) $balance, 2, '.', '') }}" value="{{ old('amount', number_format((float) $balance, 2, '.', '')) }}" data-payment-bob-amount data-payment-exchange-rate="{{ $exchangeRate }}" required>
            <div class="form-hint" data-payment-usd-reference>
                @if ($exchangeRate > 0)
                    Referencia USD: {{ $money((float) old('amount', $balance) / $exchangeRate, 'USD') }}
                @else
                    Sin tipo de cambio vigente para referencia USD.
                @endif
            </div>
            @error('amount', 'reservationPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label class="form-label" for="reservation-payment-reference">Referencia</label>
            <input class="form-control @error('reference', 'reservationPayment') is-invalid @enderror" id="reservation-payment-reference" name="reference" value="{{ old('reference') }}" placeholder="Comprobante externo, QR, transferencia, nota">
            @error('reference', 'reservationPayment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-4">
        <button class="btn btn-link link-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-success" type="submit" @disabled($paymentMethods->isEmpty() || (float) $balance <= 0)>
            <i class="ti ti-cash-register me-1"></i>Registrar adelanto
        </button>
    </div>
</form>
