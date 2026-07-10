@extends('layouts.admin')

@section('title', 'Configuraciones | '.config('app.name', 'Base Admin'))
@section('page-title', 'Configuraciones')
@section('page-subtitle', 'Parametros economicos propios de la liga')

@section('content')
    <div class="row g-3">
        @forelse ($companies as $company)
            @php
                $setting = $company->leagueSetting;
            @endphp
            <div class="col-12">
                <form class="card" method="POST" action="{{ route('league-settings.update') }}">
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
                            @foreach ($fields as $field => $meta)
                                <div class="col-sm-6 col-xl-4">
                                    <label class="form-label" for="setting-{{ $company->id }}-{{ $field }}">
                                        <i class="ti {{ $meta['icon'] }} me-1"></i>
                                        {{ $meta['label'] }}
                                    </label>
                                    <input
                                        class="form-control text-end @error($field) is-invalid @enderror"
                                        id="setting-{{ $company->id }}-{{ $field }}"
                                        name="{{ $field }}"
                                        type="number"
                                        min="0"
                                        max="99999999.99"
                                        step="0.01"
                                        value="{{ old($field, number_format((float) ($setting?->{$field} ?? 0), 2, '.', '')) }}"
                                        @cannot('league-settings.update') readonly @endcannot
                                        required
                                    >
                                    @error($field)
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
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
