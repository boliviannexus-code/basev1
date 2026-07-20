<section class="public-hero page-hero" style="--hero-image: url('{{ $publicImages['banner'] ?: '' }}');">
    <div class="public-shell">
        <nav class="public-nav">
            <a class="brand" href="{{ route('public.league', request()->route('tenant')) }}">
                @if ($company->logo_display_url)
                    <img src="{{ $company->logo_display_url }}" alt="{{ $company->name }}">
                @endif
                <span>{{ $company->name }}</span>
            </a>
            <div>
                <a href="{{ route('public.league', request()->route('tenant')) }}">Inicio</a>
                <a href="{{ route('public.standings', request()->route('tenant')) }}">Tabla de posiciones</a>
                <a href="{{ route('public.matches', request()->route('tenant')) }}">Partidos</a>
                <a href="{{ route('public.kardex', request()->route('tenant')) }}">Consulta jugador</a>
                <a class="login-link" href="{{ $loginUrl }}">Ingresar</a>
            </div>
        </nav>

        <div class="page-hero-copy">
            <p class="eyebrow">{{ $eyebrow ?? 'Liga deportiva' }}</p>
            <h1>{{ $heading }}</h1>
            @isset($subheading)
                <p class="hero-copy">{{ $subheading }}</p>
            @endisset
        </div>
    </div>
</section>
