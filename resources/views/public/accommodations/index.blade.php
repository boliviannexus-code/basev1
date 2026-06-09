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
@endsection
