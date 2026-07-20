@include('public.partials.header', ['title' => $company->public_page_title ?: $company->name])

<section class="public-hero" style="--hero-image: url('{{ $publicImages['banner'] ?: '' }}');">
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

        <div class="hero-grid">
            <div>
                <p class="eyebrow">Liga deportiva</p>
                <h1>{{ $company->public_page_title ?: $company->name }}</h1>
                <p class="hero-copy">{{ $company->public_page_summary ?: $company->interest_data ?: 'Resultados, partidos y trayectoria deportiva en un solo lugar.' }}</p>
                <div class="hero-actions">
                    <a href="{{ route('public.matches', request()->route('tenant')) }}">Ver partidos</a>
                    <a class="secondary" href="{{ route('public.standings', request()->route('tenant')) }}">Tabla de posiciones</a>
                    @if ($whatsappUrl)
                        <a class="secondary" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">WhatsApp</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</section>

<main class="public-shell content-section">
    <section class="public-card wide">
        <h2>Informacion institucional</h2>
        <p>{{ $company->public_page_body ?: $company->interest_data ?: 'Contenido en preparacion.' }}</p>
    </section>

    <section class="wide">
        <div class="public-card" style="margin-bottom:16px;">
            <h2>Ultima jornada registrada</h2>
            <p>{{ $latestMatchday?->name ?: 'Aun no hay jornadas con partidos programados.' }}</p>
            @if($latestMatchday)
                <a class="whatsapp-button" style="background:var(--field);" href="{{ route('public.matches', [request()->route('tenant'), 'filter_mode' => 'matchday', 'matchday_id' => $latestMatchday->id]) }}">Ver fixture completo</a>
            @endif
        </div>
        @include('public.partials.fixture-board', [
            'matches' => $latestMatches,
            'emptyMessage' => 'Aun no hay partidos programados para mostrar.',
        ])
    </section>

    <section class="image-pair">
        @foreach ([$publicImages['image_one'], $publicImages['image_two']] as $image)
            <div class="photo-tile">
                @if ($image)
                    <img src="{{ $image }}" alt="{{ $company->name }}">
                @else
                    <div class="photo-placeholder">Imagen de la liga</div>
                @endif
            </div>
        @endforeach
    </section>

    <section class="quick-links">
        <a href="{{ route('public.standings', request()->route('tenant')) }}">Consultar tabla de posiciones</a>
        <a href="{{ route('public.matches', request()->route('tenant')) }}">Ver partidos programados</a>
        <a href="{{ route('public.kardex', request()->route('tenant')) }}">Buscar kardex de jugador</a>
    </section>

    <section class="public-card">
        <h2>Contacto</h2>
        <p>{{ $company->public_contact_text ?: 'Comunicate con la liga para mas informacion.' }}</p>
        @if ($whatsappUrl)
            <a class="whatsapp-button" href="{{ $whatsappUrl }}" target="_blank" rel="noopener">Contactar por WhatsApp</a>
        @endif
        <p class="muted">{{ $company->public_whatsapp ?: $company->phone ?: '' }} {{ $company->email ? '· '.$company->email : '' }}</p>
        @if ($socialLinks->isNotEmpty())
            <div class="social-links">
                @foreach ($socialLinks as $label => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
                @endforeach
            </div>
        @endif
    </section>
</main>

@include('public.partials.footer')
