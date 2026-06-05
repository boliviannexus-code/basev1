<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Espacios' }} | Nido</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="public-site">
    <header class="public-header">
        <nav class="container-xl public-nav" aria-label="Principal">
            <a class="public-brand" href="{{ route('public.accommodations.index') }}">
                <span class="public-brand-mark">N</span>
                <span>Nido</span>
            </a>
            <div class="public-nav-actions">
                @auth
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('public.reservations.index') }}">
                        <i class="ti ti-calendar-check"></i>
                        Mis reservas
                    </a>
                    <form action="{{ route('logout') }}" method="post">
                        @csrf
                        <button class="btn btn-outline-dark btn-sm" type="submit">
                            <i class="ti ti-logout"></i>
                            Salir
                        </button>
                    </form>
                @else
                    <a class="btn btn-outline-dark btn-sm" href="{{ route('login') }}">
                        <i class="ti ti-user-circle"></i>
                        Ingresar
                    </a>
                @endauth
            </div>
        </nav>
    </header>

    <main>
        @yield('content')
    </main>
</body>
</html>
