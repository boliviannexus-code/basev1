<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Base Admin'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="layout-fluid">
<script>
    if (localStorage.getItem('app-sidebar-collapsed') === '1') {
        document.body.classList.add('app-sidebar-collapsed');
    }
</script>
<div class="page">
    @include('layouts.partials.sidebar')
    <div class="app-sidebar-backdrop" data-sidebar-backdrop aria-hidden="true"></div>

    <div class="page-wrapper app-wrapper">
        @include('layouts.partials.navbar')

        <div class="page-body">
            <div class="container-xl">
                <x-admin.flash />

                <div wire:loading.class="opacity-75">
                    @yield('content')
                </div>
            </div>
        </div>

        @include('layouts.partials.footer')
    </div>
</div>

@auth
    @if (auth()->user()?->company_id && auth()->user()?->can('occupancy.manage'))
        <div data-checkout-alerts
             data-index-url="{{ route('checkout-alerts.index') }}"
             data-snooze-url="{{ route('checkout-alerts.snooze', ['stay' => '__STAY__']) }}"
             data-snooze-minutes="{{ auth()->user()?->company?->check_out_alert_snooze_minutes ?? 30 }}"></div>
    @endif
@endauth

<div class="modal modal-blur fade" id="ajaxModal" tabindex="-1" aria-labelledby="ajaxModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="ajaxModalTitle">Detalle</h2>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" data-modal-body></div>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
