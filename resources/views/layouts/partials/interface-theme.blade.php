@php
    $themeCompany = \App\Support\CompanyContext::activeCompany(auth()->user());
    $theme = $themeCompany?->interfaceThemeVariables() ?? [];
    $hexToRgb = static function (?string $hex): ?string {
        if (! is_string($hex) || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
            return null;
        }

        return implode(', ', [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ]);
    };
    $primary = $theme['primary'] ?? null;
    $secondary = $theme['secondary'] ?? null;
    $accent = $theme['accent'] ?? null;
    $sidebar = $theme['sidebar'] ?? null;
    $loginBackground = $theme['login_background'] ?? null;
@endphp

@if ($primary || $secondary || $accent || $sidebar || $loginBackground)
    <style>
        :root {
            @if ($primary)
                --tblr-primary: {{ $primary }};
                --tblr-primary-rgb: {{ $hexToRgb($primary) }};
                --tblr-link-color: {{ $primary }};
            @endif
            @if ($secondary)
                --league-secondary: {{ $secondary }};
                --league-secondary-rgb: {{ $hexToRgb($secondary) }};
            @endif
            @if ($accent)
                --league-accent: {{ $accent }};
                --league-accent-rgb: {{ $hexToRgb($accent) }};
            @endif
            @if ($sidebar)
                --league-sidebar: {{ $sidebar }};
                --league-sidebar-rgb: {{ $hexToRgb($sidebar) }};
            @endif
            @if ($loginBackground)
                --league-login-background: {{ $loginBackground }};
                --league-login-background-rgb: {{ $hexToRgb($loginBackground) }};
            @endif
        }
    </style>
@endif
