<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php($loginCompany = \App\Support\CompanyContext::activeCompany())
    <title>Login | {{ $loginCompany?->name ?? config('app.name', 'Base Admin') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('layouts.partials.interface-theme')
    <style>
        .auth-login-body {
            min-height: 100vh;
            background: var(--league-login-background, #f4f7f5);
        }

        .auth-login-shell {
            display: grid;
            min-height: 100vh;
            grid-template-columns: minmax(0, .95fr) minmax(26rem, 1.05fr);
        }

        .auth-login-hero {
            display: flex;
            align-items: end;
            padding: clamp(2rem, 6vw, 4rem);
            background:
                linear-gradient(180deg, rgba(var(--league-secondary-rgb, 14, 87, 68), .2), rgba(var(--league-sidebar-rgb, 11, 55, 49), .92)),
                linear-gradient(135deg, var(--tblr-primary, #0f7b5f) 0%, var(--league-sidebar, #20443d) 55%, var(--league-accent, #f5c542) 100%);
            color: #fff;
        }

        .auth-login-hero > div {
            max-width: 36rem;
        }

        .auth-login-mark {
            display: inline-grid;
            width: 3.25rem;
            height: 3.25rem;
            place-items: center;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: .5rem;
            background: rgba(255, 255, 255, .12);
            font-size: 1.7rem;
        }

        .auth-login-kicker {
            margin: 0 0 .6rem;
            font-size: .78rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .auth-login-hero h1 {
            max-width: 34rem;
            margin: 0;
            font-size: clamp(2.4rem, 6vw, 4.6rem);
            font-weight: 900;
            line-height: .98;
            letter-spacing: 0;
        }

        .auth-login-hero p:last-child {
            max-width: 31rem;
            margin: 1rem 0 0;
            color: rgba(255, 255, 255, .86);
            font-size: 1.06rem;
            line-height: 1.55;
        }

        .auth-login-panel {
            display: grid;
            place-items: center;
            padding: 2rem;
        }

        .auth-login-card {
            width: min(100%, 28rem);
            padding: clamp(1.35rem, 4vw, 2rem);
            border: 1px solid rgba(23, 33, 28, .1);
            border-radius: .5rem;
            background: #fff;
            box-shadow: 0 22px 60px rgba(15, 23, 42, .12);
        }

        @media (max-width: 860px) {
            .auth-login-shell {
                grid-template-columns: 1fr;
            }

            .auth-login-hero {
                min-height: 18rem;
            }
        }
    </style>
</head>
<body class="auth-login-body">
<main class="auth-login-shell">
    <section class="auth-login-hero" aria-label="Bienvenida">
        <div>
            <span class="auth-login-mark"><i class="ti ti-ball-football"></i></span>
            <p class="auth-login-kicker">Panel deportivo</p>
            <h1>{{ $loginCompany?->name ?? config('app.name', 'Base Admin') }}</h1>
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

            <form method="POST" action="{{ url('/login') }}">
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
