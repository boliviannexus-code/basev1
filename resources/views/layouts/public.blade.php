<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Tours'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="public-body">
@php($publicWebsite = app(\App\Services\WebsiteContentService::class)->settings())
<header class="public-navbar">
    <nav class="container-xl d-flex align-items-center justify-content-between gap-3 py-3">
        <a class="public-brand" href="{{ route('public.home') }}" aria-label="Ir al inicio">
            @if ($publicWebsite->logo_url)
                <img class="public-brand-logo" src="{{ $publicWebsite->logo_url }}" alt="{{ config('app.name', 'Tours') }}">
            @else
                <span class="public-brand-mark"><i class="ti ti-map-pin-star"></i></span>
            @endif
            <span>{{ config('app.name', 'Tours') }}</span>
        </a>
        <div class="d-flex align-items-center gap-2 gap-md-3">
            <a class="public-nav-link d-none d-sm-inline-flex" href="{{ route('public.tours.index') }}">Explorar tours</a>
            @auth
                @if (auth()->user()->hasRole('tourist'))
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('tourist.reservations.index') }}">Mis reservas</a>
                @else
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('dashboard') }}">Admin</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-link public-nav-link p-0" type="submit">Cerrar sesion</button>
                </form>
            @else
                <a class="public-nav-link" href="{{ route('login') }}">Ingresar</a>
                <a class="btn btn-primary btn-sm" href="{{ route('tourist.register') }}">Crear cuenta</a>
            @endauth
        </div>
    </nav>
</header>

<main>
    <x-admin.flash />
    @yield('content')
</main>

<footer class="public-footer">
    <div class="container-xl d-flex flex-column flex-md-row justify-content-between gap-3 py-4">
        <div>
            <strong>{{ config('app.name', 'Tours') }}</strong>
            <p class="mb-0 text-muted">Experiencias locales, reservas claras y soporte cercano.</p>
        </div>
        <div class="d-flex gap-3 text-muted">
            <span><i class="ti ti-shield-check me-1"></i>Reserva segura</span>
            <span><i class="ti ti-brand-whatsapp me-1"></i>WhatsApp</span>
        </div>
    </div>
</footer>

@if ($publicWebsite->popup_enabled && ($publicWebsite->popup_title || $publicWebsite->popup_body))
    <div class="public-popup" data-public-popup hidden>
        <div class="public-popup-dialog" role="dialog" aria-modal="true" aria-labelledby="public-popup-title">
            @if ($publicWebsite->popup_image_url)
                <img src="{{ $publicWebsite->popup_image_url }}" alt="{{ $publicWebsite->popup_title ?: 'Oferta destacada' }}">
            @endif
            <div class="public-popup-content">
                <button class="btn btn-icon btn-sm public-popup-close" type="button" data-public-popup-close aria-label="Cerrar oferta">
                    <i class="ti ti-x"></i>
                </button>
                @if ($publicWebsite->popup_title)
                    <h2 id="public-popup-title">{{ $publicWebsite->popup_title }}</h2>
                @endif
                @if ($publicWebsite->popup_body)
                    <p>{{ $publicWebsite->popup_body }}</p>
                @endif
                @if ($publicWebsite->popup_cta_label && $publicWebsite->popup_cta_url)
                    <a class="btn btn-primary" href="{{ $publicWebsite->popup_cta_url }}">{{ $publicWebsite->popup_cta_label }}</a>
                @endif
            </div>
        </div>
    </div>
@endif
@stack('scripts')
</body>
</html>
