@extends('layouts.public', ['title' => $seo['title'], 'seo' => $seo])

@section('content')
    @php
        $displayName = $company->public_name ?: $company->name;
        $mainImage = $gallery->first()['url'] ?? null;
        $priceText = $package->price_display_text ?: money_format_decimal($package->price).' '.$package->currency;
        $badgeItems = collect($package->badges)->filter();
        $serviceIcon = function ($service, string $fallback): string {
            $icon = trim((string) $service->icon);

            if ($icon === '') {
                return $fallback;
            }

            if (str_starts_with($icon, 'ti ')) {
                return $icon;
            }

            return str_starts_with($icon, 'ti-') ? 'ti '.$icon : 'ti ti-'.$icon;
        };
    @endphp

    <section class="container-xl public-package-detail">
        <a class="public-back-link" href="{{ route('public.company.show', $company->public_slug) }}#paquetes">
            <i class="ti ti-arrow-left"></i>
            Volver a {{ $displayName }}
        </a>

        <div class="public-package-detail-hero">
            <div>
                <p class="public-eyebrow">Paquete turístico</p>
                <h1>{{ $package->name }}</h1>
                <p class="public-location"><i class="ti ti-map-pin"></i>{{ $location ?: 'Ubicación por confirmar' }}</p>
                @if ($package->short_description)
                    <p class="public-package-detail-lead">{{ $package->short_description }}</p>
                @endif
                <div class="company-public-actions">
                    @if ($whatsappUrl)
                        <a class="btn btn-success" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">
                            <i class="ti ti-brand-whatsapp"></i>
                            Consultar por WhatsApp
                        </a>
                    @endif
                    @if ($reservationUrl)
                        <a class="btn btn-dark" href="{{ $reservationUrl }}">
                            <i class="ti ti-calendar-check"></i>
                            Solicitar reserva
                        </a>
                    @endif
                </div>
            </div>

            <aside class="public-package-detail-price">
                <span>Precio desde</span>
                <strong>{{ $priceText }}</strong>
                <small>{{ $package->nights_included }} noche{{ $package->nights_included === 1 ? '' : 's' }} · incluye {{ $package->included_people }} persona{{ $package->included_people === 1 ? '' : 's' }}</small>
            </aside>
        </div>

        <div class="public-package-detail-gallery">
            <div class="public-package-detail-main-image">
                @if ($mainImage)
                    <img src="{{ $mainImage }}" alt="{{ $package->name }}">
                @else
                    <span><i class="ti ti-gift"></i></span>
                @endif
            </div>
            @if ($gallery->count() > 1)
                <div class="public-package-detail-thumbs">
                    @foreach ($gallery->skip(1)->take(4) as $image)
                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                    @endforeach
                </div>
            @endif
        </div>

        <div class="public-package-detail-grid">
            <article class="public-detail-main">
                <section>
                    <h2>Descripción</h2>
                    @if ($package->commercial_description)
                        <p>{{ $package->commercial_description }}</p>
                    @else
                        <p>{{ $package->short_description }}</p>
                    @endif
                </section>

                <section>
                    <h2>Datos del paquete</h2>
                    <dl class="company-public-dl">
                        <div><dt>Duración</dt><dd>{{ $package->nights_included }} noche{{ $package->nights_included === 1 ? '' : 's' }}</dd></div>
                        <div><dt>Categoría</dt><dd>{{ $category ? str($category)->replace('_', ' ')->title() : '-' }}</dd></div>
                        <div><dt>Ubicación</dt><dd>{{ $location ?: '-' }}</dd></div>
                        <div><dt>Personas incluidas</dt><dd>{{ $package->included_people }}</dd></div>
                        <div><dt>Máx. personas</dt><dd>{{ $package->max_people ?: '-' }}</dd></div>
                        @if ($package->extra_person_price)
                            <div><dt>Persona extra</dt><dd>{{ money_format_decimal($package->extra_person_price) }} {{ $package->currency }}</dd></div>
                        @endif
                    </dl>
                </section>

                <section>
                    <h2>Ubicación del paquete</h2>

                    @if ($package->spaces->isEmpty())
                        <p>Este paquete aún no tiene espacios activos asociados.</p>
                    @else
                        <div class="company-public-location-grid public-package-location-grid">
                            <div class="company-space-location-list">
                                @foreach ($package->spaces as $space)
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
                                            </dl>
                                        @else
                                            <p>Este espacio todavía no tiene ubicación registrada.</p>
                                        @endif
                                    </article>
                                @endforeach
                            </div>

                            <aside
                                class="company-public-map-panel"
                                data-public-company-map
                                data-google-maps-key="{{ $googleMapsKey }}"
                                data-locations='@json($packageMapLocations)'
                            >
                                @if (filled($googleMapsKey) && $packageMapLocations->contains(fn ($location) => filled($location['latitude']) && filled($location['longitude'])))
                                    <div class="company-public-map-canvas" data-public-company-map-canvas></div>
                                @else
                                    <div class="company-public-map-placeholder">
                                        <i class="ti ti-map-2"></i>
                                        <strong>Mapa no disponible</strong>
                                        <span>{{ blank($googleMapsKey) ? 'Configura Google Maps para mostrar el mapa público.' : 'Agrega coordenadas a los espacios para activar el mapa.' }}</span>
                                    </div>
                                @endif
                            </aside>
                        </div>
                    @endif
                </section>

                @if ($badgeItems->isNotEmpty())
                    <section>
                        <h2>Etiquetas</h2>
                        <div class="public-chip-list">
                            @foreach ($badgeItems as $badge)
                                <span>{{ $badge }}</span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($includedServices->isNotEmpty() || $optionalServices->isNotEmpty() || $excludedServices->isNotEmpty())
                    <section>
                        <h2>Incluye y condiciones de servicios</h2>
                        <div class="public-package-detail-services">
                            @if ($includedServices->isNotEmpty())
                                <div>
                                    <h3>Incluye</h3>
                                    <ul>
                                        @foreach ($includedServices as $service)
                                            <li>
                                                <i class="{{ $serviceIcon($service, 'ti ti-check') }}"></i>
                                                <span>
                                                    <strong>{{ $service->pivot->custom_name ?: $service->name }}</strong>
                                                    @if ($service->pivot->custom_description)
                                                        <small>{{ $service->pivot->custom_description }}</small>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($optionalServices->isNotEmpty())
                                <div>
                                    <h3>Opcionales</h3>
                                    <ul>
                                        @foreach ($optionalServices as $service)
                                            <li>
                                                <i class="{{ $serviceIcon($service, 'ti ti-plus') }}"></i>
                                                <span>
                                                    <strong>{{ $service->pivot->custom_name ?: $service->name }}</strong>
                                                    @if ($service->pivot->custom_description)
                                                        <small>{{ $service->pivot->custom_description }}</small>
                                                    @endif
                                                    @if ($service->pivot->additional_price)
                                                        <small>Adicional: {{ money_format_decimal($service->pivot->additional_price) }} {{ $package->currency }}</small>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if ($excludedServices->isNotEmpty())
                                <div>
                                    <h3>No incluye</h3>
                                    <ul>
                                        @foreach ($excludedServices as $service)
                                            <li>
                                                <i class="{{ $serviceIcon($service, 'ti ti-x') }}"></i>
                                                <span>
                                                    <strong>{{ $service->pivot->custom_name ?: $service->name }}</strong>
                                                    @if ($service->pivot->custom_description)
                                                        <small>{{ $service->pivot->custom_description }}</small>
                                                    @endif
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif

                @if ($package->conditions)
                    <section>
                        <h2>Políticas</h2>
                        <p>{{ $package->conditions }}</p>
                    </section>
                @endif
            </article>

            <aside class="public-booking-panel public-package-detail-aside">
                <h2>{{ $displayName }}</h2>
                <div class="public-live-quote">
                    <span>Precio desde</span>
                    <strong>{{ $priceText }}</strong>
                    <small>{{ $package->nights_included }} noche{{ $package->nights_included === 1 ? '' : 's' }}</small>
                </div>

                @if ($whatsappUrl)
                    <a class="btn btn-success w-100" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">
                        <i class="ti ti-brand-whatsapp"></i>
                        Consultar por WhatsApp
                    </a>
                @endif

                @if ($reservationUrl)
                    <a class="btn btn-dark w-100 mt-2" href="{{ $reservationUrl }}">
                        <i class="ti ti-calendar-check"></i>
                        Solicitar reserva
                    </a>
                @endif

                <hr>

                <dl class="public-reservation-dl">
                    @if ($contact['phone'])
                        <div><dt>Teléfono</dt><dd>{{ $contact['phone'] }}</dd></div>
                    @endif
                    @if ($contact['email'])
                        <div><dt>Email</dt><dd>{{ $contact['email'] }}</dd></div>
                    @endif
                    @if ($location)
                        <div><dt>Ubicación</dt><dd>{{ $location }}</dd></div>
                    @endif
                </dl>
            </aside>
        </div>
    </section>
@endsection
