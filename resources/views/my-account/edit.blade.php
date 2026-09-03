@extends('layouts.admin')

@section('title', 'Mi cuenta | '.config('app.name', 'Base Admin'))
@section('page-title', 'Mi cuenta')
@section('page-subtitle', 'Administra tus credenciales de acceso y seguridad')

@section('content')
    <div class="row g-3 justify-content-center">
        <div class="col-12">
            <div class="card">
                <div class="card-body d-flex flex-column flex-md-row align-items-md-center gap-3">
                    <span class="avatar avatar-lg bg-primary-lt text-primary" aria-hidden="true">
                        {{ str(auth()->user()->name)->substr(0, 1)->upper() }}
                    </span>
                    <div class="flex-fill">
                        <h2 class="h3 mb-1">{{ auth()->user()->name }}</h2>
                        <p class="text-body-secondary mb-0">{{ auth()->user()->email }}</p>
                    </div>
                    <span class="badge text-bg-success align-self-start align-self-md-center">
                        <i class="ti ti-shield-check me-1"></i>Cuenta activa
                    </span>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-6">
            <x-ui.form-panel>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <span class="avatar bg-blue-lt text-blue" aria-hidden="true"><i class="ti ti-lock"></i></span>
                    <div>
                        <h2 class="h3 mb-1">Cambiar contraseña</h2>
                        <p class="text-body-secondary mb-0">Usa al menos 8 caracteres y evita contraseñas fáciles de adivinar.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('my-account.password.update') }}" autocomplete="off" novalidate>
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label" for="password_current">Contraseña actual</label>
                        <input class="form-control @error('current_password', 'passwordUpdate') is-invalid @enderror" id="password_current" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password', 'passwordUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="password">Nueva contraseña</label>
                        <input class="form-control @error('password', 'passwordUpdate') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
                        @error('password', 'passwordUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirmar nueva contraseña</label>
                        <input class="form-control @error('password_confirmation', 'passwordUpdate') is-invalid @enderror" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
                        @error('password_confirmation', 'passwordUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-device-floppy me-1"></i>Guardar contraseña
                    </button>
                </form>
            </x-ui.form-panel>
        </div>

        <div class="col-12 col-lg-6">
            <x-ui.form-panel>
                <div class="d-flex align-items-start gap-3 mb-4">
                    <span class="avatar bg-green-lt text-green" aria-hidden="true"><i class="ti ti-cash"></i></span>
                    <div>
                        <h2 class="h3 mb-1">Código de caja</h2>
                        <p class="text-body-secondary mb-0">Este código personal autoriza cobros, ingresos y egresos asociados a tu caja.</p>
                    </div>
                </div>

                <div class="alert alert-info" role="note">
                    <i class="ti ti-info-circle me-1"></i>
                    {{ auth()->user()->transaction_pin ? 'Ya tienes un código configurado. Al guardar, será reemplazado.' : 'Todavía no tienes un código de caja configurado.' }}
                </div>

                <form method="POST" action="{{ route('my-account.transaction-pin.update') }}" autocomplete="off" novalidate>
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label class="form-label" for="pin_current_password">Contraseña actual</label>
                        <input class="form-control @error('current_password', 'transactionPinUpdate') is-invalid @enderror" id="pin_current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password', 'transactionPinUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label" for="transaction_pin">Nuevo código</label>
                            <input class="form-control @error('transaction_pin', 'transactionPinUpdate') is-invalid @enderror" id="transaction_pin" name="transaction_pin" type="password" inputmode="numeric" autocomplete="new-password" pattern="[0-9]{4}" maxlength="4" required>
                            @error('transaction_pin', 'transactionPinUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label" for="transaction_pin_confirmation">Confirmar código</label>
                            <input class="form-control @error('transaction_pin_confirmation', 'transactionPinUpdate') is-invalid @enderror" id="transaction_pin_confirmation" name="transaction_pin_confirmation" type="password" inputmode="numeric" autocomplete="new-password" pattern="[0-9]{4}" maxlength="4" required>
                            @error('transaction_pin_confirmation', 'transactionPinUpdate')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <button class="btn btn-primary" type="submit">
                        <i class="ti ti-device-floppy me-1"></i>Guardar código de caja
                    </button>
                </form>
            </x-ui.form-panel>
        </div>
    </div>
@endsection
