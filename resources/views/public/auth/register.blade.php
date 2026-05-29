@extends('layouts.public')

@section('title', 'Crear cuenta turista')

@section('content')
<section class="auth-public">
    <div class="auth-panel">
        <span class="eyebrow">Cuenta turista</span>
        <h1>Crea tu cuenta para reservar tours</h1>
        <form method="POST" action="{{ route('tourist.register.store') }}">
            @csrf
            <label class="form-label" for="name">Nombre completo</label>
            <input class="form-control mb-3 @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required>
            @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <label class="form-label" for="email">Email</label>
            <input class="form-control mb-3 @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" required>
            @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <label class="form-label" for="password">Password</label>
            <input class="form-control mb-3 @error('password') is-invalid @enderror" id="password" name="password" type="password" required>
            @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <label class="form-label" for="password_confirmation">Confirmar password</label>
            <input class="form-control mb-4" id="password_confirmation" name="password_confirmation" type="password" required>

            <button class="btn btn-primary w-100" type="submit">Crear cuenta</button>
        </form>
        <p class="mt-3 mb-0 text-muted">Ya tienes cuenta? <a href="{{ route('login') }}">Ingresar</a></p>
    </div>
</section>
@endsection
