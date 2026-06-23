@extends('layouts.admin')

@section('title', 'Nuevo alojamiento | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo alojamiento')
@section('page-subtitle', 'Selecciona la modalidad del espacio')

@section('content')
    <div data-refresh-container>
    <form method="POST" action="{{ $formAction }}" data-ajax-form novalidate>
        @csrf
        <div class="row g-3">
            <div class="col-lg-6">
                <label class="form-selectgroup-item flex-fill">
                    <input class="form-selectgroup-input" name="space_mode" type="radio" value="privado" @checked(old('space_mode', $selectedMode) === 'privado')>
                    <div class="form-selectgroup-label d-flex align-items-start p-3">
                        <span class="me-3"><i class="ti ti-home fs-1 text-primary"></i></span>
                        <span>
                            <span class="d-block fw-semibold">Privado</span>
                            <span class="d-block text-body-secondary">El huesped alquila todo el espacio completo. Ideal para casas, departamentos, cabañas o suites completas.</span>
                        </span>
                    </div>
                </label>
            </div>
            <div class="col-lg-6">
                <label class="form-selectgroup-item flex-fill">
                    <input class="form-selectgroup-input" name="space_mode" type="radio" value="compartido" @checked(old('space_mode', $selectedMode) === 'compartido')>
                    <div class="form-selectgroup-label d-flex align-items-start p-3">
                        <span class="me-3"><i class="ti ti-building-community fs-1 text-primary"></i></span>
                        <span>
                            <span class="d-block fw-semibold">Compartido</span>
                            <span class="d-block text-body-secondary">El huesped reserva una habitacion o una cama dentro de un hotel, hostal, residencia u hospedaje.</span>
                        </span>
                    </div>
                </label>
            </div>
        </div>
        @error('space_mode')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
        <div class="d-flex justify-content-end mt-4">
            <button class="btn btn-primary" type="submit">Continuar</button>
        </div>
    </form>
    </div>
@endsection
