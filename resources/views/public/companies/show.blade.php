@extends('layouts.public', ['title' => $seo['title'], 'seo' => $seo])

@section('content')
    @php
        $displayName = $company->public_name ?: $company->name;
        $location = collect([$company->city, $company->country])->filter()->implode(', ');
        $fallbackCover = 'linear-gradient(135deg, #183b63 0%, #f4c35a 58%, #2f8f83 100%)';
    @endphp

    <section class="company-public-hero" style="{{ $media['cover_image'] ? 'background-image: linear-gradient(90deg, rgba(10, 18, 32, .76), rgba(10, 18, 32, .28)), url('.$media['cover_image'].');' : 'background: '.$fallbackCover.';' }}">
        <div class="container-xl company-public-hero-inner">
            <div class="company-public-hero-copy">
                @if ($media['logo'])
                    <img class="company-public-logo" src="{{ $media['logo'] }}" alt="{{ $displayName }}">
                @else
                    <span class="company-public-logo-placeholder">{{ str($displayName)->substr(0, 1)->upper() }}</span>
                @endif

                <p class="public-eyebrow">Página pública</p>
                <h1>{{ $displayName }}</h1>

                @if ($location)
                    <p class="company-public-location"><i class="ti ti-map-pin"></i>{{ $location }}</p>
                @endif

                @if ($company->public_description)
                    <p class="company-public-summary">{{ str($company->public_description)->limit(190) }}</p>
                @endif

                <div class="company-public-actions">
                    @if ($contact['whatsapp_url'])
                        <a class="btn btn-success" href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">
                            <i class="ti ti-brand-whatsapp"></i>
                            WhatsApp
                        </a>
                    @endif
                    <a class="btn btn-light" href="#paquetes">
                        <i class="ti ti-ticket"></i>
                        Ver paquetes
                    </a>
                </div>
            </div>
        </div>
    </section>

    <section class="container-xl company-public-section company-public-overview" id="sobre">
        <article class="company-public-panel">
            <p class="public-eyebrow">Sobre la empresa</p>
            <h2>{{ $displayName }}</h2>
            <p>{{ $company->public_description ?: 'Esta empresa todavía está preparando su descripción pública.' }}</p>
        </article>

        <aside class="company-public-panel company-public-contact-card">
            <p class="public-eyebrow">Contacto</p>
            <ul>
                @if ($contact['whatsapp'])
                    <li><i class="ti ti-brand-whatsapp"></i><span>{{ $contact['whatsapp'] }}</span></li>
                @endif
                @if ($contact['phone'])
                    <li><i class="ti ti-phone"></i><span>{{ $contact['phone'] }}</span></li>
                @endif
                @if ($contact['email'])
                    <li><i class="ti ti-mail"></i><span>{{ $contact['email'] }}</span></li>
                @endif
                @if ($contact['website'])
                    <li><i class="ti ti-world"></i><a href="{{ $contact['website'] }}" target="_blank" rel="noopener noreferrer">{{ $contact['website'] }}</a></li>
                @endif
            </ul>
        </aside>
    </section>

    <section class="container-xl company-public-section" id="paquetes">
        <div class="public-results-heading">
            <div>
                <p class="public-eyebrow mb-1">Paquetes disponibles</p>
                <h2>Experiencias y estadías</h2>
            </div>
            <span class="text-body-secondary">{{ $packages->count() }} paquete{{ $packages->count() === 1 ? '' : 's' }}</span>
        </div>

        @if ($packages->isEmpty())
            <div class="public-empty">
                <i class="ti ti-ticket-off"></i>
                <h3>Sin paquetes publicados</h3>
                <p>La empresa aún no tiene paquetes activos disponibles.</p>
            </div>
        @else
            <div class="company-package-grid">
                @foreach ($packages as $package)
                    @php
                        $packageSpace = $package->spaces->first();
                        $packageSpaceNames = $package->spaces
                            ->map(fn ($space) => $space->title ?: $space->name)
                            ->filter()
                            ->values();
                        $packageLocation = $packageSpace?->location
                            ? collect([$packageSpace->location->city, $packageSpace->location->country])->filter()->implode(', ')
                            : $location;
                        $category = $package->services->first()?->type ?: collect($package->badges)->filter()->first();
                        $detailUrl = route('public.company.packages.show', [$company->public_slug, $package->slug]);
                    @endphp

                    <article class="company-package-card" id="paquete-{{ $package->id }}">
                        <div class="company-package-media">
                            @if ($package->main_image)
                                <img src="{{ Storage::disk('public')->url($package->main_image) }}" alt="{{ $package->name }}">
                            @else
                                <span><i class="ti ti-gift"></i></span>
                            @endif
                        </div>
                        <div class="company-package-body">
                            <div class="public-package-badges">
                                @if ($package->is_featured)
                                    <span>Destacado</span>
                                @endif
                            </div>

                            <h3>{{ $package->name }}</h3>
                            <p>{{ $package->short_description }}</p>
                            @if ($packageSpaceNames->isNotEmpty())
                                <p class="company-package-spaces">
                                    <i class="ti ti-building-estate"></i>
                                    {{ $packageSpaceNames->count() === 1 ? 'Espacio' : 'Espacios' }}:
                                    {{ $packageSpaceNames->join(', ') }}
                                </p>
                            @endif

                            <div class="company-package-facts">
                                <span><i class="ti ti-currency-dollar"></i>{{ $package->price_display_text ?: 'Desde '.money_format_decimal($package->price).' '.$package->currency }}</span>
                                <span><i class="ti ti-moon"></i>{{ $package->nights_included }} noche{{ $package->nights_included === 1 ? '' : 's' }}</span>
                                @if ($packageLocation)
                                    <span><i class="ti ti-map-pin"></i>{{ $packageLocation }}</span>
                                @endif
                            </div>

                            @if ($package->commercial_description)
                                <p class="company-package-commercial">{{ $package->commercial_description }}</p>
                            @endif

                            <div class="company-package-actions">
                                @if ($detailUrl)
                                    <a class="btn btn-outline-dark btn-sm" href="{{ $detailUrl }}">Ver detalle</a>
                                @endif
                                @if ($contact['whatsapp_url'])
                                    <a class="btn btn-success btn-sm" href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer">
                                        <i class="ti ti-brand-whatsapp"></i>
                                        WhatsApp
                                    </a>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    <section class="container-xl company-public-section company-public-location-grid" id="ubicacion">
        <article class="company-public-panel">
            <p class="public-eyebrow">Ubicaciones</p>
            <h2>Espacios de {{ $displayName }}</h2>

            @if ($spaces->isEmpty())
                <p>La empresa aún no tiene espacios activos con ubicación pública.</p>
            @else
                <div class="company-space-location-list">
                    @foreach ($spaces as $space)
                        @php
                            $spaceLocation = $space->location;
                            $locationId = 'space-'.$space->id;
                        @endphp
                        <article
                            class="company-space-location-item"
                            role="button"
                            tabindex="0"
                            data-public-company-location
                            data-location-id="{{ $locationId }}"
                            aria-label="Ver ubicación de {{ $space->title ?: $space->name }} en el mapa"
                        >
                            <h3>{{ $space->title ?: $space->name }}</h3>
                            @if ($spaceLocation)
                                <p>{{ collect([$spaceLocation->city, $spaceLocation->country])->filter()->implode(', ') ?: 'Ubicación registrada' }}</p>
                                <dl class="company-public-dl">
                                    <div><dt>Dirección</dt><dd>{{ $spaceLocation->address_text ?: $spaceLocation->address ?: '-' }}</dd></div>
                                    <div><dt>Referencia</dt><dd>{{ $spaceLocation->reference_text ?: $spaceLocation->reference ?: '-' }}</dd></div>
                                    {{-- <div><dt>Coordenadas</dt><dd>{{ $spaceLocation->latitude && $spaceLocation->longitude ? $spaceLocation->latitude.', '.$spaceLocation->longitude : '-' }}</dd></div> --}}
                                </dl>
                            @else
                                <p>Este espacio todavía no tiene ubicación registrada.</p>
                            @endif
                        </article>
                    @endforeach
                </div>
            @endif
        </article>

        <aside
            class="company-public-map-panel"
            data-public-company-map
            data-google-maps-key="{{ $googleMapsKey }}"
            data-locations='@json($mapLocations)'
        >
            @if (filled($googleMapsKey) && $mapLocations->contains(fn ($location) => filled($location['latitude']) && filled($location['longitude'])))
                <div class="company-public-map-canvas" data-public-company-map-canvas></div>
            @else
                <div class="company-public-map-placeholder">
                    <i class="ti ti-map-2"></i>
                    <strong>Mapa no disponible</strong>
                    <span>{{ blank($googleMapsKey) ? 'Configura Google Maps para mostrar el mapa público.' : 'Agrega coordenadas a tus espacios para activar el mapa.' }}</span>
                </div>
            @endif
        </aside>
    </section>

    <section class="container-xl company-public-section company-public-socials" id="contacto">
        <div>
            <p class="public-eyebrow">Redes y contacto</p>
            <h2>Conecta con {{ $displayName }}</h2>
        </div>
        <div class="company-public-social-links">
            @if ($contact['facebook_url'])
                <a href="{{ $contact['facebook_url'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-facebook"></i>Facebook</a>
            @endif
            @if ($contact['instagram_url'])
                <a href="{{ $contact['instagram_url'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-instagram"></i>Instagram</a>
            @endif
            @if ($contact['tiktok_url'])
                <a href="{{ $contact['tiktok_url'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-tiktok"></i>TikTok</a>
            @endif
            @if ($contact['whatsapp_url'])
                <a href="{{ $contact['whatsapp_url'] }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-brand-whatsapp"></i>WhatsApp</a>
            @endif
        </div>
    </section>

    <footer class="company-public-footer">
        <div class="container-xl">
            <span>{{ $displayName }}</span>
            <span>Powered by NIDO</span>
        </div>
    </footer>
@endsection
