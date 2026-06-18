@extends('layouts.public', ['title' => $result['title']])

@section('content')
    @php
        $space = $result['space'];
        $photos = $space->photos ?? collect();
        $selectedGuests = (int) ($filters['guests'] ?? 1);
        $selectedCheckIn = $filters['check_in'] ?? now()->toDateString();
        $selectedCheckOut = $filters['check_out'] ?? now()->addDay()->toDateString();
        $selectedPackageId = filled($filters['package_id'] ?? null) ? (int) $filters['package_id'] : null;
        $selectedPackage = $selectedPackageId ? $space->accommodationPackages->firstWhere('id', $selectedPackageId) : null;
        $packageBaseQuery = collect($result['query'])->except('package_id')->all();
        $reservationQuery = $selectedPackage ? $result['query'] : $packageBaseQuery;
        $sharedSelectionEnabled = $result['mode'] === 'shared' && filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null);
        $datesUnavailable = filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null) && ! $result['is_available'];
        $availabilityCalendar = collect($result['availability_calendar'] ?? []);
        $availableDateItems = $availabilityCalendar->where('status', 'available')->values();
        $unavailableDateItems = $availabilityCalendar->whereIn('status', ['occupied', 'unavailable'])->values();
        $publicCalendar = collect($result['public_calendar'] ?? []);
        $publicCalendarMonths = $publicCalendar->groupBy(fn ($day) => \Carbon\CarbonImmutable::parse($day['date'])->translatedFormat('F Y'));
        $publicCalendarAvailableCount = $publicCalendar->where('status', 'available')->count();
        $publicCalendarUnavailableCount = $publicCalendar->whereIn('status', ['occupied', 'unavailable'])->count();

        $packageIcon = function ($service): string {
            if (filled($service->icon)) {
                $icon = trim((string) $service->icon);

                if (str_starts_with($icon, 'ti ')) {
                    return $icon;
                }

                return str_starts_with($icon, 'ti-') ? 'ti '.$icon : 'ti ti-'.$icon;
            }

            return match ($service->type) {
                'alojamiento' => 'ti ti-home',
                'transporte' => 'ti ti-car',
                'alimentacion' => 'ti ti-tools-kitchen-2',
                'tour' => 'ti ti-map',
                'bienestar' => 'ti ti-spa',
                'decoracion' => 'ti ti-sparkles',
                'aventura' => 'ti ti-mountain',
                'equipamiento' => 'ti ti-backpack',
                default => 'ti ti-circle-check',
            };
        };
    @endphp

    <section class="container-xl public-detail">
        <a class="public-back-link" href="{{ route('public.accommodations.search', $result['query']) }}">
            <i class="ti ti-arrow-left"></i>
            Volver a resultados
        </a>

        <div class="public-detail-heading">
            <div>
                <span class="public-type-badge {{ $result['mode'] === 'shared' ? 'is-shared' : 'is-private' }}">{{ $result['label'] }}</span>
                <h1>{{ $result['title'] }}</h1>
                <p class="public-location"><i class="ti ti-map-pin"></i>{{ $result['location'] }}</p>
            </div>
            <div class="public-detail-price">
                <span>{{ $result['mode'] === 'shared' ? 'Precio de la habitacion' : 'Precio del espacio' }}</span>
                <strong>{{ $result['price_from'] !== null ? money_format_decimal($result['price_from']) : 'Sin tarifa' }}</strong>
                <small>por noche</small>
            </div>
        </div>

        <div class="public-gallery">
            <div class="public-gallery-main">
                @if ($result['image_url'])
                    <button class="public-gallery-zoom" type="button" data-public-gallery-zoom data-src="{{ $result['image_url'] }}" data-alt="{{ $result['title'] }}">
                        <img src="{{ $result['image_url'] }}" alt="{{ $result['title'] }}">
                        <span><i class="ti ti-zoom-in"></i> </span>
                    </button>
                @else
                    <span><i class="ti ti-home"></i></span>
                @endif
            </div>
            <div class="public-gallery-thumbs">
                @foreach ($photos->skip(1)->take(4) as $photo)
                    @php($photoUrl = Storage::disk('public')->url($photo->path))
                    <button class="public-gallery-zoom" type="button" data-public-gallery-zoom data-src="{{ $photoUrl }}" data-alt="{{ $photo->alt_text ?: $result['title'] }}">
                        <img src="{{ $photoUrl }}" alt="{{ $photo->alt_text ?: $result['title'] }}">
                        <span><i class="ti ti-zoom-in"></i> </span>
                    </button>
                @endforeach
            </div>
        </div>

        <div class="public-detail-grid">
            <article class="public-detail-main">
                <section>
                    <h2>Informacion general</h2>
                    <div class="public-detail-facts">
                        <span><i class="ti ti-users"></i>{{ $result['capacity'] }} persona{{ $result['capacity'] === 1 ? '' : 's' }}</span>
                        @if ($space->bedrooms_count)
                            <span><i class="ti ti-bed"></i>{{ $space->bedrooms_count }} dormitorio{{ $space->bedrooms_count === 1 ? '' : 's' }}</span>
                        @endif
                        @if ($space->beds_count)
                            <span><i class="ti ti-bed-filled"></i>{{ $space->beds_count }} cama{{ $space->beds_count === 1 ? '' : 's' }}</span>
                        @endif
                    </div>
                    @if ($space->short_description)
                        <p class="lead">{{ $space->short_description }}</p>
                    @endif
                    @if ($space->full_description)
                        <p>{{ $space->full_description }}</p>
                    @endif
                </section>

                <section>
                    <h2>Por que reservar aqui</h2>
                    <div class="public-benefit-grid">
                        <span><i class="ti ti-receipt"></i>Precio por noche y total visible antes de crear cuenta.</span>
                        <span><i class="ti ti-shield-check"></i>Tu reserva se confirma despues de validar el pago.</span>
                        <span><i class="ti ti-qrcode"></i>Reserva con adelanto por QR, sin pago completo inmediato.</span>
                    </div>
                </section>

                @if ($space->generalServices->isNotEmpty())
                    <section>
                        <h2>Servicios</h2>
                        <div class="public-chip-list">
                            @foreach ($space->generalServices as $service)
                                <span>{{ $service->name }}</span>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($result['mode'] === 'private' && $space->accommodationPackages->isNotEmpty())
                    <section>
                        <div class="public-package-section-heading">
                            <h2>Paquetes disponibles</h2>
                            <span class="{{ $availabilityCalendar->isEmpty() ? 'd-none' : '' }} {{ $datesUnavailable ? 'is-unavailable' : '' }}" data-public-availability-badge>
                                <i class="ti {{ $datesUnavailable ? 'ti-calendar-x' : 'ti-calendar-check' }}"></i>
                                <strong data-public-availability-badge-text>{{ $datesUnavailable ? 'Fechas no disponibles' : 'Fechas disponibles' }}</strong>
                            </span>
                        </div>
                        @if ($datesUnavailable)
                            <div class="public-empty public-package-unavailable">
                                <i class="ti ti-calendar-x"></i>
                                <h3>Fechas no disponibles</h3>
                                <p>Prueba con otro rango de fechas para ver paquetes disponibles.</p>
                                @if ($availabilityCalendar->isNotEmpty())
                                    <div class="public-availability-breakdown">
                                        <div>
                                            <strong>Fechas disponibles</strong>
                                            @if ($availableDateItems->isNotEmpty())
                                                <ul>
                                                    @foreach ($availableDateItems as $day)
                                                        <li><span>{{ $day['display_date'] }}</span><small>{{ $day['label'] }}</small></li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p>No hay fechas disponibles en este rango.</p>
                                            @endif
                                        </div>
                                        <div>
                                            <strong>Fechas ocupadas</strong>
                                            @if ($unavailableDateItems->isNotEmpty())
                                                <ul>
                                                    @foreach ($unavailableDateItems as $day)
                                                        <li><span>{{ $day['display_date'] }}</span><small>{{ $day['label'] }}</small></li>
                                                    @endforeach
                                                </ul>
                                            @else
                                                <p>No hay fechas ocupadas en este rango.</p>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="public-package-grid">
                                @foreach ($space->accommodationPackages as $package)
                                    @php($includedServices = $package->services->where('pivot.inclusion_type', 'included')->take(5))
                                    @php($allIncludedServices = $package->services->where('pivot.inclusion_type', 'included')->values())
                                    @php($optionalServices = $package->services->where('pivot.inclusion_type', 'optional_paid')->values())
                                    @php($excludedServices = $package->services->where('pivot.inclusion_type', 'not_included')->values())
                                    @php($badges = collect($package->badges)->filter()->whenEmpty(fn ($items) => $items->push('Todo incluido')))
                                    @php($priceText = $package->price_display_text ?: money_format_decimal($package->price).' '.$package->currency.' por paquete / incluye '.$package->included_people.' persona'.($package->included_people === 1 ? '' : 's'))
                                    @php($packageReservationUrl = route('public.accommodations.show', ['space' => $space, ...$packageBaseQuery, 'package_id' => $package->id]).'#booking-panel')
                                    @php($packageModalId = 'public-package-modal-'.$package->id)
                                    @php($packageGallery = collect()
                                        ->when($package->main_image, fn ($items) => $items->push([
                                            'url' => Storage::disk('public')->url($package->main_image),
                                            'alt' => $package->name,
                                        ]))
                                        ->merge($space->photos->map(fn ($photo) => [
                                            'url' => Storage::disk('public')->url($photo->path),
                                            'alt' => $photo->alt_text ?: ($space->title ?: $space->name ?: $package->name),
                                        ]))
                                        ->unique('url')
                                        ->values())
                                    <article class="public-package-card {{ $selectedPackageId === $package->id ? 'is-selected' : '' }}">
                                        <div class="public-package-image">
                                            @if ($package->main_image)
                                                <img src="{{ Storage::disk('public')->url($package->main_image) }}" alt="{{ $package->name }}">
                                            @else
                                                <span><i class="ti ti-gift"></i></span>
                                            @endif
                                        </div>
                                        <div>
                                            <div class="public-package-badges">
                                                @foreach ($badges->take(4) as $badge)
                                                    <span>{{ $badge }}</span>
                                                @endforeach
                                            </div>
                                            <div class="d-flex align-items-start justify-content-between gap-2">
                                                <h3>{{ $package->name }}</h3>
                                                @if ($package->is_featured)
                                                    <span class="public-type-badge is-private">Destacado</span>
                                                @endif
                                            </div>
                                            <p>{{ $package->short_description }}</p>
                                            @if ($package->commercial_description)
                                                <p class="public-package-commercial">{{ $package->commercial_description }}</p>
                                            @endif
                                            @if ($package->video_url)
                                                <a class="public-package-video" href="{{ $package->video_url }}" target="_blank" rel="noopener noreferrer">
                                                    <i class="ti ti-player-play"></i>
                                                    Ver video del paquete
                                                </a>
                                            @endif
                                            <div class="public-package-price">
                                                <strong>{{ $priceText }}</strong>
                                                <small>{{ $package->nights_included }} noche{{ $package->nights_included === 1 ? '' : 's' }} incluida{{ $package->nights_included === 1 ? '' : 's' }}</small>
                                            </div>
                                            <dl class="public-inline-summary">
                                                <div><dt>Personas incluidas</dt><dd>{{ $package->included_people }}</dd></div>
                                                <div><dt>Max. personas</dt><dd>{{ $package->extra_person_price ? $space->max_capacity : ($package->max_people ?: $space->max_capacity) }}</dd></div>
                                                @if ($package->extra_person_price)
                                                    <div><dt>Persona extra</dt><dd>{{ money_format_decimal($package->extra_person_price) }} {{ $package->currency }}</dd></div>
                                                @endif
                                            </dl>
                                            @if ($includedServices->isNotEmpty())
                                                <div class="public-package-services">
                                                    @foreach ($includedServices as $service)
                                                        <span><i class="{{ $packageIcon($service) }}"></i>{{ $service->pivot->custom_name ?: $service->name }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            <div class="public-package-conditions">
                                                <strong>Condiciones</strong>
                                                <p>{{ $package->conditions ?: 'Sujeto a disponibilidad del espacio privado y validacion del adelanto.' }}</p>
                                            </div>
                                            <p class="public-package-confirmation"><i class="ti ti-qrcode"></i>La reserva se confirma despues de validar el adelanto por QR.</p>
                                            <div class="public-package-actions">
                                                <button class="btn btn-outline-dark" type="button" data-bs-toggle="modal" data-bs-target="#{{ $packageModalId }}">
                                                    <i class="ti ti-list-details"></i>
                                                    Ver detalle
                                                </button>
                                                <button class="btn btn-dark" type="button" data-public-package-select="{{ $package->id }}">
                                                    <i class="ti ti-calendar-check"></i>
                                                    Elegir paquete
                                                </button>
                                            </div>
                                        </div>
                                    </article>

                                    <div class="modal fade public-package-modal" id="{{ $packageModalId }}" tabindex="-1" aria-labelledby="{{ $packageModalId }}-title" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <div>
                                                        <p class="public-eyebrow mb-1">Detalle del paquete</p>
                                                        <h3 class="modal-title" id="{{ $packageModalId }}-title">{{ $package->name }}</h3>
                                                    </div>
                                                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="public-package-modal-summary">
                                                        <div class="public-package-modal-gallery">
                                                            @if ($packageGallery->isNotEmpty())
                                                                @foreach ($packageGallery as $image)
                                                                    <button class="public-package-modal-media" type="button" data-public-gallery-zoom data-src="{{ $image['url'] }}" data-alt="{{ $image['alt'] }}">
                                                                        <img src="{{ $image['url'] }}" alt="{{ $image['alt'] }}">
                                                                        <span><i class="ti ti-zoom-in"></i></span>
                                                                    </button>
                                                                @endforeach
                                                            @else
                                                                <div class="public-package-modal-media">
                                                                    <span><i class="ti ti-gift"></i></span>
                                                                </div>
                                                            @endif
                                                        </div>
                                                        <div>
                                                            <div class="public-package-badges">
                                                                @foreach ($badges->take(4) as $badge)
                                                                    <span>{{ $badge }}</span>
                                                                @endforeach
                                                            </div>
                                                            @if ($package->short_description)
                                                                <p>{{ $package->short_description }}</p>
                                                            @endif
                                                            @if ($package->commercial_description)
                                                                <p class="public-package-commercial">{{ $package->commercial_description }}</p>
                                                            @endif
                                                            @if ($package->video_url)
                                                                <a class="public-package-modal-video" href="{{ $package->video_url }}" target="_blank" rel="noopener noreferrer">
                                                                    <i class="ti ti-player-play"></i>
                                                                    Ver video del paquete en nueva pestaña
                                                                    <i class="ti ti-external-link"></i>
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <div class="public-package-modal-facts">
                                                        <div><span>Precio</span><strong>{{ $priceText }}</strong></div>
                                                        <div><span>Noches incluidas</span><strong>{{ $package->nights_included }}</strong></div>
                                                        <div><span>Personas incluidas</span><strong>{{ $package->included_people }}</strong></div>
                                                        <div><span>Máximo personas</span><strong>{{ $package->extra_person_price ? $space->max_capacity : ($package->max_people ?: $space->max_capacity) }}</strong></div>
                                                        @if ($package->extra_person_price)
                                                            <div><span>Persona extra</span><strong>{{ money_format_decimal($package->extra_person_price) }} {{ $package->currency }}</strong></div>
                                                        @endif
                                                    </div>

                                                    <div class="public-package-modal-services">
                                                        @if ($allIncludedServices->isNotEmpty())
                                                            <section>
                                                                <h4>Incluye</h4>
                                                                <div class="public-package-services">
                                                                    @foreach ($allIncludedServices as $service)
                                                                        <span><i class="{{ $packageIcon($service) }}"></i>{{ $service->pivot->custom_name ?: $service->name }}</span>
                                                                    @endforeach
                                                                </div>
                                                            </section>
                                                        @endif

                                                        @if ($optionalServices->isNotEmpty())
                                                            <section>
                                                                <h4>Opcionales</h4>
                                                                <div class="public-package-services">
                                                                    @foreach ($optionalServices as $service)
                                                                        <span><i class="{{ $packageIcon($service) }}"></i>{{ $service->pivot->custom_name ?: $service->name }}@if ($service->pivot->additional_price) · {{ money_format_decimal($service->pivot->additional_price) }} {{ $package->currency }}@endif</span>
                                                                    @endforeach
                                                                </div>
                                                            </section>
                                                        @endif

                                                        @if ($excludedServices->isNotEmpty())
                                                            <section>
                                                                <h4>No incluye</h4>
                                                                <div class="public-package-services">
                                                                    @foreach ($excludedServices as $service)
                                                                        <span><i class="{{ $packageIcon($service) }}"></i>{{ $service->pivot->custom_name ?: $service->name }}</span>
                                                                    @endforeach
                                                                </div>
                                                            </section>
                                                        @endif
                                                    </div>

                                                    <div class="public-package-conditions">
                                                        <strong>Condiciones</strong>
                                                        <p>{{ $package->conditions ?: 'Sujeto a disponibilidad del espacio privado y validacion del adelanto.' }}</p>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    @if ($package->video_url)
                                                        <a class="btn btn-outline-primary" href="{{ $package->video_url }}" target="_blank" rel="noopener noreferrer">
                                                            <i class="ti ti-player-play"></i>
                                                            Ver video en nueva pestaña
                                                        </a>
                                                    @endif
                                                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
                                                    <button class="btn btn-dark" type="button" data-bs-dismiss="modal" data-public-package-select="{{ $package->id }}">
                                                        <i class="ti ti-calendar-check"></i>
                                                        Elegir este paquete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif

                @if ($result['mode'] === 'shared' && $result['rooms']->isNotEmpty())
                    <section>
                        <h2>Habitaciones disponibles</h2>
                        <form class="public-room-list" action="{{ route('public.reservations.start') }}" method="get" data-shared-room-selection data-guests="{{ $selectedGuests }}" data-nights="{{ filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null) ? max(\Carbon\CarbonImmutable::parse($filters['check_in'])->diffInDays(\Carbon\CarbonImmutable::parse($filters['check_out'])), 1) : 1 }}">
                            <input type="hidden" name="space_id" value="{{ $space->id }}">
                            <input type="hidden" name="check_in" value="{{ $selectedCheckIn }}">
                            <input type="hidden" name="check_out" value="{{ $selectedCheckOut }}">
                            <input type="hidden" name="guests" value="{{ $selectedGuests }}">
                            @foreach ($result['rooms'] as $room)
                                @php($roomQuote = $roomQuotes->get($room->id))
                                @php($roomCapacity = $room->max_capacity ?: $room->beds->sum('total_capacity'))
                                @php($roomPrice = $roomQuote ? (float) $roomQuote['price_per_night'] : 0)
                                @php($roomSubtotal = $roomQuote ? (float) $roomQuote['total_amount'] : 0)
                                @php($canSellFullRoom = in_array($room->sale_mode, ['full_room', 'flexible'], true))
                                @php($canSellBeds = in_array($room->sale_mode, ['bed_unit', 'flexible'], true))
                                @php($availableBedUnits = $room->relationLoaded('availableBedUnits') ? $room->getRelation('availableBedUnits') : $room->bedUnits->where('status', 'active')->values())
                                @php($showFullRoomOption = $canSellFullRoom && $roomQuote)
                                <article class="public-room-row">
                                    <span>
                                        <strong>{{ $room->title ?: $room->name }}</strong>
                                        @if ($room->description)
                                            <p>{{ $room->description }}</p>
                                        @endif
                                        @if ($showFullRoomOption)
                                            <dl class="public-inline-summary">
                                                <div><dt>Habitacion/noche</dt><dd>{{ money_format_decimal($roomQuote['price_per_night']) }} Bs</dd></div>
                                                <div><dt>Total</dt><dd>{{ money_format_decimal($roomQuote['total_amount']) }} Bs</dd></div>
                                                <div><dt>Adelanto</dt><dd>{{ money_format_decimal($roomQuote['advance_amount']) }} Bs</dd></div>
                                            </dl>
                                        @endif
                                    </span>
                                    <span class="public-room-actions">
                                        <span>{{ $roomCapacity }} persona{{ $roomCapacity === 1 ? '' : 's' }}</span>
                                        @if ($result['rooms_available'] <= 2)
                                            <span class="public-soft-urgency"><i class="ti ti-clock"></i>Pocas habitaciones</span>
                                        @endif
                                        @if ($sharedSelectionEnabled && $showFullRoomOption)
                                            <label class="public-room-check" data-shared-room-option data-selection-type="full_room" data-capacity="{{ $roomCapacity }}" data-price="{{ $roomPrice }}" data-subtotal="{{ $roomSubtotal }}">
                                                <span>Completa</span>
                                                <input class="form-check-input" type="checkbox" name="space_room_ids[]" value="{{ $room->id }}" data-shared-room-checkbox>
                                            </label>
                                        @endif
                                    </span>
                                    @if ($canSellBeds && $availableBedUnits->isNotEmpty())
                                        <div class="public-bed-unit-list">
                                            @foreach ($availableBedUnits as $bedUnit)
                                                @php($bedQuote = $bedUnitQuotes->get($bedUnit->id))
                                                @php($bedCapacity = $bedQuote ? (int) $bedQuote['capacity'] : (int) ($bedUnit->bedType?->capacity ?: 1))
                                                @php($bedPrice = $bedQuote ? (float) $bedQuote['price_per_night'] : 0)
                                                @php($bedSubtotal = $bedQuote ? (float) $bedQuote['total_amount'] : 0)
                                                <label class="public-bed-unit-row" data-shared-room-option data-selection-type="bed_unit" data-capacity="{{ $bedCapacity }}" data-price="{{ $bedPrice }}" data-subtotal="{{ $bedSubtotal }}">
                                                    <span>
                                                        <strong>{{ $bedUnit->label }}</strong>
                                                        <small>{{ $bedUnit->bedType?->name ?: 'Cama' }} · {{ $bedCapacity }} persona{{ $bedCapacity === 1 ? '' : 's' }}</small>
                                                    </span>
                                                    @if ($bedQuote)
                                                        <span>{{ money_format_decimal($bedQuote['price_per_night']) }} Bs/noche</span>
                                                    @else
                                                        <span>Sin tarifa</span>
                                                    @endif
                                                    @if ($sharedSelectionEnabled && $bedQuote)
                                                        <input class="form-check-input" type="checkbox" name="room_bed_unit_ids[]" value="{{ $bedUnit->id }}" data-shared-room-checkbox>
                                                    @endif
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                            @if ($sharedSelectionEnabled)
                                <div class="public-live-quote" data-shared-room-summary>
                                    <span data-shared-room-summary-count>Selecciona habitaciones o camas</span>
                                    <strong data-shared-room-summary-total>Bs 0.00</strong>
                                    <small data-shared-room-summary-detail>Capacidad seleccionada: 0 de {{ $selectedGuests }} persona{{ $selectedGuests === 1 ? '' : 's' }}</small>
                                    <button class="btn btn-dark w-100" type="submit" data-shared-room-submit disabled>
                                        Reservar seleccion
                                    </button>
                                </div>
                            @endif
                        </form>
                    </section>
                @endif

                <section>
                    <h2>Reglas de reserva</h2>
                    <div class="public-rule-grid">
                        <span><i class="ti ti-clock"></i>La solicitud queda pendiente hasta validar el adelanto.</span>
                        <span><i class="ti ti-qrcode"></i>El pago por QR se revisa manualmente por el establecimiento.</span>
                        <span><i class="ti ti-calendar-check"></i>Las fechas se bloquean solo mientras el estado lo permite.</span>
                    </div>
                </section>
            </article>

            <aside class="public-booking-panel" id="booking-panel">
                <h2>Calcula tu reserva</h2>
                <p data-public-availability-note>{{ $result['availability_note'] }} Elige solo habitacion o un paquete antes de continuar.</p>

                @if ($publicCalendar->isNotEmpty())
                    <button class="btn btn-outline-dark w-100 public-calendar-action" type="button" data-bs-toggle="modal" data-bs-target="#public-availability-calendar-modal">
                        <i class="ti ti-calendar-month"></i>
                        Ver calendario disponible
                    </button>
                @endif

                <form class="public-booking-form" action="{{ route('public.accommodations.quote', ['space' => $space]) }}" method="get" data-public-booking-ajax data-price="{{ $selectedPackage ? (float) $selectedPackage->price : ($result['price_from'] ?? 0) }}" data-booking-type="{{ $selectedPackage ? 'package' : 'normal' }}" data-included-people="{{ $selectedPackage?->included_people ?? 0 }}" data-extra-person-price="{{ $selectedPackage?->extra_person_price ?? 0 }}">
                    @if (filled($filters['destination'] ?? null))
                        <input type="hidden" name="destination" value="{{ $filters['destination'] }}">
                    @endif
                    <div class="public-booking-mode">
                        <label class="form-label" for="detail_package_id">Modalidad</label>
                        <select class="form-select" id="detail_package_id" name="package_id" data-public-package-field>
                            <option value="">Solo habitacion</option>
                            @foreach ($space->accommodationPackages as $package)
                                <option value="{{ $package->id }}" @selected($selectedPackageId === $package->id)>
                                    {{ $package->name }} · {{ money_format_decimal($package->price) }} {{ $package->currency }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="detail_check_in">Ingreso</label>
                        <input class="form-control" id="detail_check_in" name="check_in" type="date" value="{{ $selectedCheckIn }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="detail_check_out">Salida</label>
                        <input class="form-control" id="detail_check_out" name="check_out" type="date" value="{{ $selectedCheckOut }}" required>
                    </div>
                    <div>
                        <label class="form-label" for="detail_guests">Personas</label>
                        <input class="form-control" id="detail_guests" name="guests" type="number" min="1" max="50" value="{{ $selectedGuests }}" required>
                    </div>
                    <button class="btn btn-outline-dark w-100" type="submit" data-public-quote-submit>
                        <i class="ti ti-calendar-search"></i>
                        Actualizar cotizacion
                    </button>
                </form>

                <dl>
                    <div><dt>Ingreso</dt><dd data-public-summary-check-in>{{ $selectedCheckIn ?: 'Sin fecha' }}</dd></div>
                    <div><dt>Salida</dt><dd data-public-summary-check-out>{{ $selectedCheckOut ?: 'Sin fecha' }}</dd></div>
                    <div><dt>Personas</dt><dd data-public-summary-guests>{{ $selectedGuests }}</dd></div>
                </dl>

                <div class="public-booking-choice" data-public-booking-choice>
                    <span>{{ $selectedPackage ? 'Paquete' : 'Solo habitacion' }}</span>
                    <strong>{{ $selectedPackage?->name ?: 'Estadia sin paquete' }}</strong>
                    <small>{{ $selectedPackage ? 'Incluye los servicios configurados del paquete.' : 'Reserva solamente el alojamiento para las fechas elegidas.' }}</small>
                </div>

                @if ($quote)
                    <div class="public-live-quote" data-public-quote>
                        <span>{{ ($quote['booking_type'] ?? 'normal') === 'package' ? 'Total final del paquete' : 'Total solo habitacion' }}</span>
                        <strong>{{ money_format_decimal($quote['total_amount']) }} Bs</strong>
                        @if (($quote['booking_type'] ?? 'normal') === 'package')
                            <small>Incluye {{ $quote['included_people'] }} persona{{ $quote['included_people'] === 1 ? '' : 's' }} · extra: {{ $quote['extra_people'] }}</small>
                        @else
                            <small>{{ $result['mode'] === 'shared' ? 'Habitacion' : 'Espacio' }}: {{ money_format_decimal($quote['price_per_night']) }} Bs por noche</small>
                        @endif
                        <dl>
                            @if (($quote['booking_type'] ?? 'normal') === 'package')
                                <div><dt>Precio base paquete</dt><dd>{{ money_format_decimal($quote['package_price']) }} Bs</dd></div>
                                <div>
                                    <dt>Personas extra</dt>
                                    <dd>{{ $quote['extra_people'] }} x {{ $quote['nights'] }} noche{{ $quote['nights'] === 1 ? '' : 's' }} = {{ money_format_decimal($quote['extra_people_total']) }} Bs</dd>
                                </div>
                                @foreach (($quote['extra_people_details'] ?? collect()) as $detail)
                                    <div>
                                        <dt>{{ $detail['date'] }}</dt>
                                        <dd>{{ $detail['quantity'] }} x {{ money_format_decimal($detail['unit_price']) }} Bs = {{ money_format_decimal($detail['total']) }} Bs</dd>
                                    </div>
                                @endforeach
                                @if (($quote['package_extra_nights'] ?? 0) > 0)
                                    <div><dt>Noches extra</dt><dd>{{ $quote['package_extra_nights'] }} · {{ money_format_decimal($quote['package_extra_nights_total']) }} Bs</dd></div>
                                @endif
                            @endif
                            <div><dt>Reserva con adelanto</dt><dd>{{ money_format_decimal($quote['advance_amount']) }} Bs</dd></div>
                            <div><dt>Saldo</dt><dd>{{ money_format_decimal($quote['balance_amount']) }} Bs</dd></div>
                        </dl>
                        <p>Tu reserva se confirma despues de validar el pago.</p>
                    </div>
                @elseif ($result['price_from'] !== null)
                    <div class="public-live-quote" id="public-estimate" data-public-quote>
                        <span>Solo habitacion</span>
                        <strong>Completa fechas</strong>
                        <small>Se validara con la disponibilidad real antes de confirmar.</small>
                    </div>
                @else
                    <div class="public-live-quote" id="public-estimate" data-public-quote>
                        <span>Solo habitacion</span>
                        <strong>Sin tarifa configurada</strong>
                        <small>El establecimiento debe configurar precio para estas fechas.</small>
                    </div>
                @endif
                <p class="public-payment-note d-none" data-public-quote-error></p>

                @if ($result['mode'] === 'private')
                    <a class="btn btn-dark w-100" href="{{ route('public.reservations.start', ['space_id' => $space->id, ...$reservationQuery, 'check_in' => $selectedCheckIn, 'check_out' => $selectedCheckOut, 'guests' => $selectedGuests]) }}" data-public-reserve-link>
                        {{ $selectedPackage ? 'Reservar este paquete' : 'Reservar con adelanto' }}
                    </a>
                @else
                    @if (filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null))
                        <p class="public-payment-note">Elige habitaciones completas o camas disponibles para continuar.</p>
                    @else
                        <a class="btn btn-dark w-100" href="{{ route('public.accommodations.show', ['space' => $space->id, ...$result['query'], 'check_in' => $selectedCheckIn, 'check_out' => $selectedCheckOut, 'guests' => $selectedGuests]) }}">
                            Ver disponibilidad
                        </a>
                    @endif
                @endif
            </aside>
        </div>
    </section>

    @if ($publicCalendar->isNotEmpty())
        <div class="modal fade public-availability-calendar-modal" id="public-availability-calendar-modal" tabindex="-1" aria-labelledby="public-availability-calendar-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <span class="public-calendar-kicker">Disponibilidad del espacio</span>
                            <h3 class="modal-title" id="public-availability-calendar-title">Calendario de disponibilidad</h3>
                        </div>
                        <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="public-calendar-summary">
                            <div>
                                <span>Disponibles</span>
                                <strong>{{ $publicCalendarAvailableCount }}</strong>
                            </div>
                            <div>
                                <span>No disponibles</span>
                                <strong>{{ $publicCalendarUnavailableCount }}</strong>
                            </div>
                            <div class="public-calendar-legend" aria-label="Leyenda del calendario">
                                <span><i class="is-available"></i> Disponible</span>
                                <span><i class="is-occupied"></i> Ocupada</span>
                                <span><i class="is-unavailable"></i> No disponible</span>
                            </div>
                        </div>
                        <div class="public-calendar-selection" data-public-calendar-selection>
                            <div>
                                <span>Ingreso</span>
                                <strong data-public-calendar-check-in>{{ $selectedCheckIn ?: 'Sin fecha' }}</strong>
                            </div>
                            <div>
                                <span>Salida</span>
                                <strong data-public-calendar-check-out>{{ $selectedCheckOut ?: 'Sin fecha' }}</strong>
                            </div>
                        </div>

                        <div class="public-calendar-months">
                            @foreach ($publicCalendarMonths as $monthLabel => $days)
                                @php($firstWeekday = \Carbon\CarbonImmutable::parse($days->first()['date'])->dayOfWeekIso)
                                <section class="public-calendar-month">
                                    <h4>{{ ucfirst($monthLabel) }}</h4>
                                    <div class="public-calendar-weekdays" aria-hidden="true">
                                        <span>Lun</span>
                                        <span>Mar</span>
                                        <span>Mie</span>
                                        <span>Jue</span>
                                        <span>Vie</span>
                                        <span>Sab</span>
                                        <span>Dom</span>
                                    </div>
                                    <div class="public-calendar-grid">
                                        @for ($blank = 1; $blank < $firstWeekday; $blank++)
                                            <span class="public-calendar-day is-blank" aria-hidden="true"></span>
                                        @endfor
                                        @foreach ($days as $day)
                                            @php($date = \Carbon\CarbonImmutable::parse($day['date']))
                                            <button class="public-calendar-day is-{{ $day['status'] }}" type="button" title="{{ $day['display_date'] }} - {{ $day['label'] }}" data-public-calendar-date="{{ $day['date'] }}" data-public-calendar-status="{{ $day['status'] }}" @disabled($day['status'] !== 'available')>
                                                <strong>{{ $date->day }}</strong>
                                                <small>{{ $day['label'] }}</small>
                                            </button>
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-outline-secondary" type="button" data-public-calendar-clear>
                            <i class="ti ti-refresh"></i>
                            Limpiar
                        </button>
                        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cerrar</button>
                        <button class="btn btn-dark" type="button" data-public-calendar-apply data-bs-dismiss="modal" disabled>
                            <i class="ti ti-calendar-search"></i>
                            Consultar estas fechas
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('.public-booking-form');
            const format = (amount) => new Intl.NumberFormat('es-BO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount);

            if (form?.matches('[data-public-booking-ajax]')) {
                const quoteBox = document.querySelector('[data-public-quote]');
                const choiceBox = document.querySelector('[data-public-booking-choice]');
                const reserveLink = document.querySelector('[data-public-reserve-link]');
                const errorBox = document.querySelector('[data-public-quote-error]');
                const availabilityNote = document.querySelector('[data-public-availability-note]');
                const availabilityBadge = document.querySelector('[data-public-availability-badge]');
                const availabilityBadgeText = document.querySelector('[data-public-availability-badge-text]');
                const submit = form.querySelector('[data-public-quote-submit]');
                const packageField = form.querySelector('[data-public-package-field]');
                const checkInField = form.querySelector('[name="check_in"]');
                const checkOutField = form.querySelector('[name="check_out"]');
                const guestsField = form.querySelector('[name="guests"]');
                const summaryCheckIn = document.querySelector('[data-public-summary-check-in]');
                const summaryCheckOut = document.querySelector('[data-public-summary-check-out]');
                const summaryGuests = document.querySelector('[data-public-summary-guests]');
                const calendarButtons = [...document.querySelectorAll('[data-public-calendar-date]')];
                const calendarCheckIn = document.querySelector('[data-public-calendar-check-in]');
                const calendarCheckOut = document.querySelector('[data-public-calendar-check-out]');
                const calendarApply = document.querySelector('[data-public-calendar-apply]');
                const calendarClear = document.querySelector('[data-public-calendar-clear]');
                let quoteTimer = null;
                let selectedCalendarCheckIn = checkInField?.value || '';
                let selectedCalendarCheckOut = checkOutField?.value || '';
                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
                const dateFormatter = new Intl.DateTimeFormat('es-BO', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric',
                    timeZone: 'UTC',
                });
                const parseDate = (value) => {
                    const [year, month, day] = String(value || '').split('-').map(Number);

                    return year && month && day ? new Date(Date.UTC(year, month - 1, day)) : null;
                };
                const formatDate = (value) => {
                    const date = parseDate(value);

                    return date ? dateFormatter.format(date) : 'Sin fecha';
                };
                const addDays = (value, days) => {
                    const date = parseDate(value);

                    if (!date) {
                        return '';
                    }

                    date.setUTCDate(date.getUTCDate() + days);

                    return date.toISOString().slice(0, 10);
                };
                const availableDates = new Set(calendarButtons
                    .filter((button) => button.dataset.publicCalendarStatus === 'available')
                    .map((button) => button.dataset.publicCalendarDate));
                const availableRange = (start, lastNight) => {
                    let cursor = start;

                    while (cursor && cursor <= lastNight) {
                        if (!availableDates.has(cursor)) {
                            return false;
                        }

                        cursor = addDays(cursor, 1);
                    }

                    return true;
                };
                const syncDateSummary = () => {
                    if (summaryCheckIn) {
                        summaryCheckIn.textContent = checkInField?.value || 'Sin fecha';
                    }
                    if (summaryCheckOut) {
                        summaryCheckOut.textContent = checkOutField?.value || 'Sin fecha';
                    }
                    if (summaryGuests) {
                        summaryGuests.textContent = guestsField?.value || '1';
                    }
                };
                const renderCalendarSelection = () => {
                    selectedCalendarCheckIn = checkInField ? checkInField.value : selectedCalendarCheckIn;
                    selectedCalendarCheckOut = checkOutField ? checkOutField.value : selectedCalendarCheckOut;

                    if (calendarCheckIn) {
                        calendarCheckIn.textContent = formatDate(selectedCalendarCheckIn);
                    }
                    if (calendarCheckOut) {
                        calendarCheckOut.textContent = formatDate(selectedCalendarCheckOut);
                    }
                    if (calendarApply) {
                        calendarApply.disabled = !selectedCalendarCheckIn || !selectedCalendarCheckOut;
                    }

                    const lastNight = selectedCalendarCheckOut ? addDays(selectedCalendarCheckOut, -1) : '';
                    calendarButtons.forEach((button) => {
                        const date = button.dataset.publicCalendarDate;
                        const inRange = selectedCalendarCheckIn
                            && lastNight
                            && date >= selectedCalendarCheckIn
                            && date <= lastNight;

                        button.classList.toggle('is-check-in', date === selectedCalendarCheckIn);
                        button.classList.toggle('is-check-out', date === lastNight);
                        button.classList.toggle('is-selected-range', Boolean(inRange));
                    });

                    syncDateSummary();
                };
                const setSelectedDates = (checkIn, checkOut) => {
                    selectedCalendarCheckIn = checkIn || '';
                    selectedCalendarCheckOut = checkOut || '';

                    if (checkInField) {
                        checkInField.value = selectedCalendarCheckIn;
                    }
                    if (checkOutField) {
                        checkOutField.value = selectedCalendarCheckOut;
                    }

                    renderCalendarSelection();
                };

                const setLoading = (loading) => {
                    form.classList.toggle('is-loading', loading);
                    if (loading && reserveLink) {
                        reserveLink.classList.add('disabled');
                        reserveLink.setAttribute('aria-disabled', 'true');
                    }
                    if (submit) {
                        submit.disabled = loading;
                        submit.innerHTML = loading
                            ? '<i class="ti ti-loader-2"></i> Calculando...'
                            : '<i class="ti ti-calendar-search"></i> Actualizar cotizacion';
                    }
                };

                const renderErrorQuote = (message, isUnavailable = false) => {
                    if (!quoteBox) {
                        return;
                    }

                    quoteBox.innerHTML = `
                        <span>${isUnavailable ? 'Fechas no disponibles' : 'Cotizacion pendiente'}</span>
                        <strong>${isUnavailable ? 'No disponible' : 'Revisa los datos'}</strong>
                        <small>${escapeHtml(message)}</small>
                    `;
                };

                const setError = (message, isUnavailable = false) => {
                    if (errorBox) {
                        errorBox.textContent = message;
                        errorBox.classList.remove('d-none');
                    }
                    renderErrorQuote(message, isUnavailable);
                    if (reserveLink) {
                        reserveLink.classList.add('disabled');
                        reserveLink.setAttribute('aria-disabled', 'true');
                        reserveLink.removeAttribute('href');
                    }
                };

                const clearError = () => {
                    if (errorBox) {
                        errorBox.textContent = '';
                        errorBox.classList.add('d-none');
                    }
                };

                const renderAvailability = (availability) => {
                    if (!availability) {
                        return;
                    }

                    if (availabilityNote && availability.note) {
                        availabilityNote.textContent = `${availability.note} Elige solo habitacion o un paquete antes de continuar.`;
                    }

                    if (!availabilityBadge) {
                        return;
                    }

                    if (!availability.badge) {
                        availabilityBadge.classList.add('d-none');

                        return;
                    }

                    availabilityBadge.classList.remove('d-none');
                    availabilityBadge.classList.toggle('is-unavailable', !availability.is_available);
                    availabilityBadge.querySelector('i')?.classList.toggle('ti-calendar-check', availability.is_available);
                    availabilityBadge.querySelector('i')?.classList.toggle('ti-calendar-x', !availability.is_available);

                    if (availabilityBadgeText) {
                        availabilityBadgeText.textContent = availability.badge;
                    }
                };

                const enableReserve = (quote) => {
                    if (reserveLink) {
                        reserveLink.classList.remove('disabled');
                        reserveLink.removeAttribute('aria-disabled');
                        reserveLink.href = quote.reservation_url;
                        reserveLink.textContent = quote.button_label;
                    }
                };

                const renderQuote = (quote) => {
                    if (choiceBox) {
                        const selected = packageField?.selectedOptions?.[0]?.textContent?.trim() || 'Solo habitacion';
                        choiceBox.querySelector('span').textContent = quote.mode_label;
                        choiceBox.querySelector('strong').textContent = quote.booking_type === 'package' ? selected : 'Estadia sin paquete';
                        choiceBox.querySelector('small').textContent = quote.booking_type === 'package'
                            ? 'Servicios y condiciones del paquete incluidos en el calculo.'
                            : 'Reserva solamente el alojamiento para las fechas elegidas.';
                    }

                    if (quoteBox) {
                        quoteBox.innerHTML = `
                            <span>${escapeHtml(quote.title)}</span>
                            <strong>${escapeHtml(quote.total)}</strong>
                            <small>${escapeHtml(quote.detail)}</small>
                            <dl>
                                ${quote.lines.map((line) => `<div><dt>${escapeHtml(line.label)}</dt><dd>${escapeHtml(line.value)}</dd></div>`).join('')}
                            </dl>
                            <p>${escapeHtml(quote.message)}</p>
                        `;
                    }

                    if (reserveLink) {
                        enableReserve(quote);
                    }
                };

                const requestQuote = async () => {
                    clearError();
                    setLoading(true);

                    try {
                        const params = new URLSearchParams(new FormData(form));
                        const response = await fetch(`${form.action}?${params.toString()}`, {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });
                        const payload = await response.json();

                        if (!response.ok || payload.success === false) {
                            renderAvailability(payload.availability);
                            setError(
                                payload.message || 'No pudimos cotizar esas fechas.',
                                payload.availability?.is_available === false,
                            );

                            return;
                        }

                        renderAvailability(payload.availability);
                        renderQuote(payload.quote);
                    } catch {
                        setError('No pudimos actualizar la cotizacion. Revisa tu conexion e intenta nuevamente.');
                    } finally {
                        setLoading(false);
                    }
                };

                const scheduleQuote = () => {
                    window.clearTimeout(quoteTimer);
                    quoteTimer = window.setTimeout(requestQuote, 350);
                };

                form.addEventListener('submit', (event) => {
                    event.preventDefault();
                    requestQuote();
                });
                form.querySelectorAll('input, select').forEach((input) => {
                    input.addEventListener('change', () => {
                        renderCalendarSelection();
                        scheduleQuote();
                    });
                    input.addEventListener('input', () => {
                        renderCalendarSelection();
                        scheduleQuote();
                    });
                });
                calendarButtons.forEach((button) => {
                    button.addEventListener('click', () => {
                        if (button.dataset.publicCalendarStatus !== 'available') {
                            return;
                        }

                        const date = button.dataset.publicCalendarDate;
                        const currentStart = selectedCalendarCheckIn;
                        const currentEnd = selectedCalendarCheckOut;

                        if (!currentStart || currentEnd || date < currentStart) {
                            setSelectedDates(date, '');

                            return;
                        }

                        const lastNight = date;
                        const checkOut = addDays(lastNight, 1);

                        if (!availableRange(currentStart, lastNight)) {
                            setSelectedDates(date, '');

                            return;
                        }

                        setSelectedDates(currentStart, checkOut);
                        scheduleQuote();
                    });
                });
                calendarClear?.addEventListener('click', () => setSelectedDates('', ''));
                calendarApply?.addEventListener('click', () => {
                    document.getElementById('booking-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    requestQuote();
                });
                document.querySelectorAll('[data-public-package-select]').forEach((button) => {
                    button.addEventListener('click', () => {
                        if (!packageField) {
                            return;
                        }

                        packageField.value = button.dataset.publicPackageSelect || '';
                        document.getElementById('booking-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        requestQuote();
                    });
                });
                renderCalendarSelection();
            }

            const sharedForm = document.querySelector('[data-shared-room-selection]');

            if (!sharedForm) {
                return;
            }

            const checkboxes = [...sharedForm.querySelectorAll('[data-shared-room-checkbox]')];
            const submit = sharedForm.querySelector('[data-shared-room-submit]');
            const count = sharedForm.querySelector('[data-shared-room-summary-count]');
            const total = sharedForm.querySelector('[data-shared-room-summary-total]');
            const detail = sharedForm.querySelector('[data-shared-room-summary-detail]');
            const requiredGuests = Number(sharedForm.dataset.guests || 1);

            const updateRooms = () => {
                const selected = checkboxes
                    .filter((checkbox) => checkbox.checked)
                    .map((checkbox) => checkbox.closest('[data-shared-room-option]'));
                const selectedType = selected[0]?.dataset.selectionType || null;
                const capacity = selected.reduce((sum, option) => sum + Number(option.dataset.capacity || 0), 0);
                const amount = selected.reduce((sum, option) => sum + Number(option.dataset.subtotal || 0), 0);
                const perNight = selected.reduce((sum, option) => sum + Number(option.dataset.price || 0), 0);
                const valid = selected.length > 0 && capacity >= requiredGuests;

                checkboxes.forEach((checkbox) => {
                    const option = checkbox.closest('[data-shared-room-option]');
                    checkbox.disabled = selectedType && !checkbox.checked && option?.dataset.selectionType !== selectedType;
                });

                const selectionLabel = selectedType === 'bed_unit' ? 'cama' : 'habitacion';
                const selectionPlural = selectedType === 'bed_unit' ? 'camas' : 'habitaciones';
                count.textContent = selected.length === 1 ? `1 ${selectionLabel} seleccionada` : `${selected.length} ${selectionPlural} seleccionadas`;
                total.textContent = `Bs ${format(amount)}`;
                detail.textContent = `Capacidad seleccionada: ${capacity} de ${requiredGuests} persona${requiredGuests === 1 ? '' : 's'} · Bs ${format(perNight)} por noche`;
                submit.disabled = !valid;
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateRooms));
            updateRooms();
        });
    </script>
@endsection
