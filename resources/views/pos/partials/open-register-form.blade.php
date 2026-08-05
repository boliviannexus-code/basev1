<div class="card form-panel {{ $class ?? '' }}">
    <div class="card-header">
        <h3 class="card-title">{{ $title ?? 'Abrir caja' }}</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('pos.open') }}" autocomplete="off" novalidate>
            @csrf

            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">Caja</label>
                    <div class="form-control-plaintext fw-semibold">
                        {{ auth()->user()?->company?->name ?? 'Empresa' }} · {{ auth()->user()?->name }}
                    </div>
                    <div class="text-body-secondary small">La caja se abrira solo para tu usuario.</div>
                </div>

                <div class="col-md-4">
                    <label class="form-label" for="opening_amount">Monto inicial</label>
                    <input class="form-control text-end @error('opening_amount') is-invalid @enderror" id="opening_amount" name="opening_amount" type="number" min="0" step="0.01" value="{{ old('opening_amount', '0.00') }}" required>
                    @error('opening_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button class="btn btn-primary" type="submit">Abrir caja</button>
            </div>
        </form>
    </div>
</div>
