@extends('layouts.public', ['title' => 'Buscar espacios'])

@section('content')
    <section class="public-hero">
        <div class="container-xl public-hero-inner">
            <div class="public-hero-copy">
                <p class="public-eyebrow">Reservas con adelanto</p>
                <h1>Encuentra una estadia lista para tus fechas</h1>
                <p>Compara hospedajes, calcula el total por noche y solicita tu reserva sin registrarte hasta decidir continuar.</p>
            </div>
            @include('public.accommodations.partials.search-form', ['filters' => $filters])
        </div>
    </section>

    @php($publicCompanies = $publicCompanies ?? collect())
    @php($spaceLocations = $spaceLocations ?? collect())

    @if (! $isSearch && $publicCompanies->isNotEmpty())
        <section class="container-xl public-companies">
            <div class="public-results-heading">
                <div>
                    <p class="public-eyebrow mb-1">Empresas en línea</p>
                    <h2>Explora empresas registradas</h2>
                </div>
                <span class="text-body-secondary">{{ $publicCompanies->count() }} empresa{{ $publicCompanies->count() === 1 ? '' : 's' }}</span>
            </div>

            <div class="public-company-grid">
                @foreach ($publicCompanies as $company)
                    <article class="public-company-card">
                        <a class="public-company-media" href="{{ route('public.company.show', $company['slug']) }}" aria-label="Ver página de {{ $company['name'] }}">
                            @if ($company['cover_url'])
                                <img src="{{ $company['cover_url'] }}" alt="Portada de {{ $company['name'] }}">
                            @else
                                <span><i class="ti ti-building-store"></i></span>
                            @endif
                        </a>

                        <div class="public-company-body">
                            <div class="public-company-heading">
                                @if ($company['logo_url'])
                                    <img class="public-company-logo" src="{{ $company['logo_url'] }}" alt="Logo de {{ $company['name'] }}">
                                @else
                                    <span class="public-company-logo-placeholder">{{ str($company['name'])->substr(0, 1)->upper() }}</span>
                                @endif

                                <div>
                                    <h3><a href="{{ route('public.company.show', $company['slug']) }}">{{ $company['name'] }}</a></h3>
                                    <p class="public-location">
                                        <i class="ti ti-map-pin"></i>{{ $company['location'] !== '' ? $company['location'] : 'Ubicaciones en sus espacios' }}
                                    </p>
                                </div>
                            </div>

                            @if ($company['description'])
                                <p class="public-description">{{ str($company['description'])->limit(125) }}</p>
                            @endif

                            <div class="public-card-facts">
                                <span><i class="ti ti-home-star"></i>{{ $company['active_spaces_count'] }} espacio{{ $company['active_spaces_count'] === 1 ? '' : 's' }}</span>
                                <span><i class="ti ti-ticket"></i>{{ $company['active_accommodation_packages_count'] }} paquete{{ $company['active_accommodation_packages_count'] === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="public-company-footer">
                                <a class="btn btn-outline-dark btn-sm" href="{{ route('public.company.show', $company['slug']) }}">
                                    Ver página
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="container-xl public-results">
        <div class="public-results-heading">
            <div>
                <p class="public-eyebrow mb-1">{{ $isSearch ? 'Resultados' : 'Sugerencias' }}</p>
                <h2>{{ $isSearch ? 'Espacios encontrados' : 'Espacios activos' }}</h2>
            </div>
            @if ($paginator)
                <span class="text-body-secondary">{{ $paginator->total() }} resultado{{ $paginator->total() === 1 ? '' : 's' }}</span>
            @endif
        </div>

        @if ($results->isEmpty())
            <div class="public-empty">
                <i class="ti ti-calendar-off"></i>
                <h3>Sin espacios disponibles</h3>
                <p>Ajusta el destino, las fechas o la cantidad de personas.</p>
            </div>
        @else
            <div class="public-card-grid">
                @foreach ($results as $result)
                    @php($space = $result['space'])
                    <article class="public-accommodation-card">
                        <a class="public-card-media" href="{{ route('public.accommodations.show', ['space' => $space, ...$result['query']]) }}">
                            @if ($result['image_url'])
                                <img src="{{ $result['image_url'] }}" alt="{{ $result['title'] }}">
                            @else
                                <span><i class="ti ti-home"></i></span>
                            @endif
                        </a>
                        <div class="public-card-body">
                            <div class="public-card-topline">
                                <span class="public-type-badge {{ $result['mode'] === 'shared' ? 'is-shared' : 'is-private' }}">{{ $result['label'] }}</span>
                                @if ($result['is_available'])
                                    <span class="public-status-badge">Disponible</span>
                                @endif
                            </div>
                            <h3><a href="{{ route('public.accommodations.show', ['space' => $space, ...$result['query']]) }}">{{ $result['title'] }}</a></h3>
                            <p class="public-location"><i class="ti ti-map-pin"></i>{{ $result['location'] }}</p>
                            <p class="public-description">{{ $space->short_description ?: str($space->full_description)->limit(120) }}</p>
                            <div class="public-benefit-strip">
                                <span><i class="ti ti-shield-check"></i>Pago revisado</span>
                                <span><i class="ti ti-calculator"></i>Total claro</span>
                            </div>
                            <div class="public-card-facts">
                                <span><i class="ti ti-users"></i>{{ $result['capacity'] }} persona{{ $result['capacity'] === 1 ? '' : 's' }}</span>
                                @if ($result['mode'] === 'shared')
                                    <span><i class="ti ti-door"></i>{{ $result['rooms_available'] }} habitacion{{ $result['rooms_available'] === 1 ? '' : 'es' }}</span>
                                    @if ($result['rooms_available'] > 0 && $result['rooms_available'] <= 2)
                                        <span class="public-soft-urgency"><i class="ti ti-clock"></i>Quedan pocas</span>
                                    @endif
                                @elseif (filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null))
                                    <span class="public-soft-urgency"><i class="ti ti-clock"></i>Disponible para estas fechas</span>
                                @endif
                            </div>
                            <div class="public-card-footer">
                                <div>
                                    <span class="public-price-label">Desde por noche</span>
                                    <strong>{{ $result['price_from'] !== null ? 'Bs '.money_format_decimal($result['price_from']) : 'Sin tarifa' }}</strong>
                                </div>
                                <a class="btn btn-outline-dark btn-sm" href="{{ route('public.accommodations.show', ['space' => $space, ...$result['query']]) }}">
                                    {{ filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null) ? 'Reservar' : 'Ver disponibilidad' }}
                                </a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($paginator)
                <div class="mt-4">
                    {{ $paginator->links() }}
                </div>
            @endif
        @endif
    </section>

    @if ($spaceLocations->isNotEmpty())
        <section class="container-xl company-public-section company-public-location-grid public-accommodations-location-grid" id="ubicaciones">
            <article class="company-public-panel">
                <p class="public-eyebrow">{{ $isSearch ? 'Mapa de resultados' : 'Mapa de sugerencias' }}</p>
                <h2>Ubicaciones de espacios</h2>

                <div class="company-space-location-list">
                    @foreach ($spaceLocations as $location)
                        <article
                            class="company-space-location-item"
                            role="button"
                            tabindex="0"
                            data-public-company-location
                            data-location-id="{{ $location['id'] }}"
                            aria-label="Ver ubicación de {{ $location['name'] }} en el mapa"
                        >
                            <h3>{{ $location['name'] }}</h3>
                            <p>{{ collect([$location['city'], $location['country']])->filter()->implode(', ') ?: 'Ubicación registrada' }}</p>
                            <dl class="company-public-dl">
                                <div><dt>Dirección</dt><dd>{{ $location['address'] ?: '-' }}</dd></div>
                                <div><dt>Referencia</dt><dd>{{ $location['reference'] ?: '-' }}</dd></div>
                            </dl>
                            <a class="btn btn-outline-dark btn-sm mt-2" href="{{ route('public.accommodations.show', ['space' => $location['space'], ...$location['result']['query']]) }}">
                                Ver disponibilidad
                            </a>
                        </article>
                    @endforeach
                </div>
            </article>

            <aside
                class="company-public-map-panel"
                data-public-company-map
                data-google-maps-key="{{ $googleMapsKey ?? config('services.google_maps.key') }}"
                data-locations='@json($spaceLocations->map(fn (array $location): array => collect($location)->except(['space', 'result'])->all())->values())'
            >
                @if (filled($googleMapsKey ?? config('services.google_maps.key')) && $spaceLocations->contains(fn (array $location): bool => filled($location['latitude']) && filled($location['longitude'])))
                    <div class="company-public-map-canvas" data-public-company-map-canvas></div>
                @else
                    <div class="company-public-map-placeholder">
                        <i class="ti ti-map-2"></i>
                        <strong>Mapa no disponible</strong>
                        <span>{{ blank($googleMapsKey ?? config('services.google_maps.key')) ? 'Configura Google Maps para mostrar el mapa público.' : 'Agrega coordenadas a los espacios para activar el mapa.' }}</span>
                    </div>
                @endif
            </aside>
        </section>
    @endif
@endsection
