@extends('layouts.admin')

@section('title', 'Precios del tour | '.config('app.name', 'Base Admin'))
@section('page-title', 'Precios del tour')
@section('page-subtitle', $tour->display_title)

@section('content')
    <x-ui.form-panel title="Tarifas en dolares">
        <form method="POST" action="{{ route('tours.pricing.update', $tour) }}">
            @csrf
            @method('PUT')

            <div class="alert alert-info">
                Los precios se registran siempre en USD y corresponden al precio por persona dentro del rango configurado.
            </div>

            <div class="vstack gap-3" data-price-rows>
                @php
                    $rows = old('prices', $tour->prices->map(fn ($price) => [
                        'title' => $price->title,
                        'min_people' => $price->min_people,
                        'max_people' => $price->max_people,
                        'price_usd' => $price->price_usd,
                    ])->values()->all() ?: [['title' => null, 'min_people' => 1, 'max_people' => null, 'price_usd' => null]]);
                @endphp

                @foreach ($rows as $index => $row)
                    <div class="row g-2 align-items-end" data-price-row>
                        <div class="col-md-3">
                            <label class="form-label">Titulo del precio</label>
                            <input class="form-control" name="prices[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" maxlength="120" placeholder="Adulto, niño, tarifa privada">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min. personas</label>
                            <input class="form-control" name="prices[{{ $index }}][min_people]" type="number" min="1" value="{{ $row['min_people'] ?? '' }}" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max. personas</label>
                            <input class="form-control" name="prices[{{ $index }}][max_people]" type="number" min="1" value="{{ $row['max_people'] ?? '' }}" placeholder="Sin limite">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Precio USD por persona</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input class="form-control" name="prices[{{ $index }}][price_usd]" type="number" min="0.01" step="0.01" value="{{ $row['price_usd'] ?? '' }}" required>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-danger w-100" type="button" data-remove-price>Quitar</button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('prices')<div class="text-danger small mt-2">{{ $message }}</div>@enderror

            <div class="d-flex justify-content-between mt-4">
                <a class="btn btn-outline-secondary" href="{{ route('tours.index') }}">Volver</a>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" type="button" data-add-price>Agregar rango</button>
                    <button class="btn btn-primary" type="submit">Guardar precios</button>
                </div>
            </div>
        </form>
    </x-ui.form-panel>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const rows = document.querySelector('[data-price-rows]');
            const add = document.querySelector('[data-add-price]');
            const reindex = () => {
                rows?.querySelectorAll('[data-price-row]').forEach((row, index) => {
                    row.querySelectorAll('input').forEach((input) => {
                        input.name = input.name.replace(/prices\[\d+]/, `prices[${index}]`);
                    });
                });
            };

            add?.addEventListener('click', () => {
                const first = rows?.querySelector('[data-price-row]');
                if (!first || !rows) return;
                const clone = first.cloneNode(true);
                clone.querySelectorAll('input').forEach((input) => input.value = '');
                rows.append(clone);
                reindex();
            });

            rows?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-price]');
                if (!button || rows.querySelectorAll('[data-price-row]').length === 1) return;
                button.closest('[data-price-row]').remove();
                reindex();
            });
        });
    </script>
@endpush
