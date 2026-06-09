@extends('layouts.admin')

@section('title', 'Tipo de cambio | '.config('app.name', 'Base Admin'))
@section('page-title', 'Tipo de cambio')
@section('page-subtitle', 'Tasa vigente para conversiones USD a BOB')

@section('content')
    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card title="Tasa vigente">
                <div class="card-body">
                    @if ($currentRate)
                        <div class="display-6 fw-semibold">{{ number_format((float) $currentRate->rate, 4) }}</div>
                        <div class="text-body-secondary">1 USD = {{ number_format((float) $currentRate->rate, 4) }} BOB</div>
                        <div class="small text-body-secondary mt-2">Vigente desde {{ $currentRate->effective_date->format('d/m/Y') }}</div>
                    @else
                        <div class="text-body-secondary">Sin tipo de cambio configurado.</div>
                    @endif
                </div>
            </x-ui.card>

            <x-ui.card class="mt-3" title="Actualizar tipo de cambio">
                <div class="card-body">
                    <form method="POST" action="{{ route('exchange-rates.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="rate">Tipo de cambio</label>
                            <input class="form-control @error('rate') is-invalid @enderror" id="rate" name="rate" type="number" min="0.0001" step="0.0001" value="{{ old('rate', $currentRate?->rate) }}" required>
                            <div class="invalid-feedback">{{ $errors->first('rate') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="effective_date">Fecha de vigencia</label>
                            <input class="form-control @error('effective_date') is-invalid @enderror" id="effective_date" name="effective_date" type="date" value="{{ old('effective_date', today()->toDateString()) }}" required>
                            <div class="invalid-feedback">{{ $errors->first('effective_date') }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="notes">Notas</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                            <div class="invalid-feedback">{{ $errors->first('notes') }}</div>
                        </div>
                        <button class="btn btn-primary w-100" type="submit">
                            <i class="ti ti-refresh me-1"></i>Actualizar
                        </button>
                    </form>
                </div>
            </x-ui.card>
        </div>

        <div class="col-lg-8">
            <x-ui.table-card title="Historial">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conversion</th>
                                <th>Estado</th>
                                <th>Usuario</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rates as $rate)
                                <tr>
                                    <td>{{ $rate->effective_date->format('d/m/Y') }}</td>
                                    <td>1 {{ $rate->from_currency }} = {{ number_format((float) $rate->rate, 4) }} {{ $rate->to_currency }}</td>
                                    <td>
                                        <span class="badge text-bg-{{ $rate->is_active ? 'success' : 'secondary' }}">{{ $rate->is_active ? 'Vigente' : 'Historico' }}</span>
                                    </td>
                                    <td>{{ $rate->creator?->name ?? '-' }}</td>
                                    <td class="text-body-secondary small">{{ $rate->notes ?: '-' }}</td>
                                </tr>
                            @empty
                                <x-ui.empty-row colspan="5" message="No hay tipos de cambio registrados." />
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-slot:footer>{{ $rates->links() }}</x-slot:footer>
            </x-ui.table-card>
        </div>
    </div>
@endsection
