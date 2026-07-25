<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | {{ config('app.name', 'Base Admin') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="auth-login-body">
<main class="auth-login-shell">
    <section class="auth-login-hero" aria-label="Bienvenida">
        <div>
            <span class="auth-login-mark"><i class="ti ti-ball-football"></i></span>
            <p class="auth-login-kicker">Panel deportivo</p>
            <h1>{{ config('app.name', 'Base Admin') }}</h1>
            <p>Administra ligas, torneos, jugadores, reportes y reservas desde un acceso protegido.</p>
        </div>
    </section>

    <section class="auth-login-panel" aria-label="Inicio de sesion">
        <div class="auth-login-card">
            <div class="mb-4">
                <p class="auth-login-kicker text-primary mb-2">Acceso seguro</p>
                <h2 class="h1 mb-1">Iniciar sesion</h2>
                <p class="text-muted mb-0">Ingresa tus credenciales para continuar.</p>
            </div>

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="email">Correo electronico</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-mail"></i></span>
                        <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Contrasena</label>
                    <div class="input-icon">
                        <span class="input-icon-addon"><i class="ti ti-lock"></i></span>
                        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="current-password" required>
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                    <label class="form-check mb-0" for="remember">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <span class="form-check-label">Recordarme</span>
                    </label>
                </div>

                <button class="btn btn-primary w-100" type="submit">
                    <i class="ti ti-login-2 me-1"></i> Ingresar
                </button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
