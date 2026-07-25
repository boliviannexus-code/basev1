@extends('layouts.admin')

@section('title', 'Apariencia | '.config('app.name', 'Base Admin'))
@section('page-title', 'Apariencia')
@section('page-subtitle', 'Colores de interfaz por liga')

@section('content')
    <div class="row g-3">
        @forelse ($companies as $company)
            <div class="col-12">
                <form class="card" method="POST" action="{{ route('league-settings.appearance.update') }}">
                    @csrf
                    <input type="hidden" name="company_id" value="{{ $company->id }}">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div>
                            <h3 class="card-title mb-0">{{ $company->name }}</h3>
                            <div class="text-body-secondary small">Codigo {{ $company->code ?: 'sin codigo' }}</div>
                        </div>
                        @can('league-settings.update')
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-device-floppy me-1"></i>
                                Guardar
                            </button>
                        @endcan
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ($colorFields as $colorField => $colorMeta)
                                @php($colorValue = old($colorField, $company->{$colorField} ?: $colorMeta['fallback']))
                                <div class="col-sm-6 col-xl">
                                    <label class="form-label" for="appearance-{{ $company->id }}-{{ $colorField }}">{{ $colorMeta['label'] }}</label>
                                    <div class="input-group">
                                        <input
                                            class="form-control form-control-color @error($colorField) is-invalid @enderror"
                                            id="appearance-{{ $company->id }}-{{ $colorField }}"
                                            name="{{ $colorField }}"
                                            type="color"
                                            value="{{ $colorValue }}"
                                            @cannot('league-settings.update') disabled @endcannot
                                        >
                                        <input class="form-control text-uppercase" value="{{ $colorValue }}" readonly>
                                        @error($colorField)
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-info">No hay ligas activas para configurar.</div>
            </div>
        @endforelse
    </div>
@endsection
