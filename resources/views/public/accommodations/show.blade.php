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
                    <img src="{{ $result['image_url'] }}" alt="{{ $result['title'] }}">
                @else
                    <span><i class="ti ti-home"></i></span>
                @endif
            </div>
            <div class="public-gallery-thumbs">
                @foreach ($photos->skip(1)->take(4) as $photo)
                    <img src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt_text ?: $result['title'] }}">
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
                        <h2>Paquetes disponibles</h2>
                        <div class="public-package-grid">
                            @foreach ($space->accommodationPackages as $package)
                                @php($includedServices = $package->services->where('pivot.inclusion_type', 'included')->take(5))
                                @php($badges = collect($package->badges)->filter()->whenEmpty(fn ($items) => $items->push('Todo incluido')))
                                @php($priceText = $package->price_display_text ?: money_format_decimal($package->price).' '.$package->currency.' por paquete / incluye '.$package->included_people.' persona'.($package->included_people === 1 ? '' : 's'))
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
                                            <div><dt>Max. personas</dt><dd>{{ $package->max_people ?: $space->max_capacity }}</dd></div>
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
                                        <a class="btn btn-outline-dark w-100 mt-3" href="{{ route('public.accommodations.show', ['space' => $space, ...$packageBaseQuery, 'package_id' => $package->id]) }}#booking-panel">
                                            Reservar este paquete
                                        </a>
                                    </div>
                                </article>
                            @endforeach
                        </div>
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

                @if ($space->location)
                    <section>
                        <h2>Ubicacion</h2>
                        <p>{{ collect([$space->location->address, $space->location->zone_or_neighborhood, $space->location->city, $space->location->state_or_region, $space->location->country])->filter()->implode(', ') }}</p>
                    </section>
                @endif
            </article>

            <aside class="public-booking-panel" id="booking-panel">
                <h2>{{ $selectedPackage ? 'Calcula tu paquete' : 'Calcula tu estadia' }}</h2>
                <p>{{ $result['availability_note'] }} Explora el total antes de registrarte.</p>

                @if ($selectedPackage)
                    <div class="public-live-quote mb-3">
                        <span>Paquete seleccionado</span>
                        <strong>{{ $selectedPackage->name }}</strong>
                        <small>{{ $selectedPackage->price_display_text ?: money_format_decimal($selectedPackage->price).' '.$selectedPackage->currency.' por paquete / incluye '.$selectedPackage->included_people.' persona'.($selectedPackage->included_people === 1 ? '' : 's') }}</small>
                        <p><strong>Condiciones:</strong> {{ $selectedPackage->conditions ?: 'Sujeto a disponibilidad del espacio privado y validacion del adelanto.' }}</p>
                        <p>La reserva se confirma despues de validar el adelanto por QR.</p>
                    </div>
                @endif

                <form class="public-booking-form" action="{{ route('public.accommodations.show', ['space' => $space]) }}" method="get" data-price="{{ $selectedPackage ? (float) $selectedPackage->price : ($result['price_from'] ?? 0) }}" data-booking-type="{{ $selectedPackage ? 'package' : 'normal' }}" data-included-people="{{ $selectedPackage?->included_people ?? 0 }}" data-extra-person-price="{{ $selectedPackage?->extra_person_price ?? 0 }}">
                    @if (filled($filters['destination'] ?? null))
                        <input type="hidden" name="destination" value="{{ $filters['destination'] }}">
                    @endif
                    @if ($selectedPackage)
                        <input type="hidden" name="package_id" value="{{ $selectedPackage->id }}">
                    @endif
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
                    <button class="btn btn-outline-dark w-100" type="submit">
                        <i class="ti ti-calendar-search"></i>
                        Ver disponibilidad
                    </button>
                </form>

                <dl>
                    <div><dt>Ingreso</dt><dd>{{ $selectedCheckIn ?: 'Sin fecha' }}</dd></div>
                    <div><dt>Salida</dt><dd>{{ $selectedCheckOut ?: 'Sin fecha' }}</dd></div>
                    <div><dt>Personas</dt><dd>{{ $selectedGuests }}</dd></div>
                </dl>

                @if ($quote)
                    <div class="public-live-quote">
                        <span>{{ ($quote['booking_type'] ?? 'normal') === 'package' ? 'Total final del paquete' : 'Total por '.$quote['nights'].' noche'.($quote['nights'] === 1 ? '' : 's') }}</span>
                        <strong>{{ money_format_decimal($quote['total_amount']) }} Bs</strong>
                        @if (($quote['booking_type'] ?? 'normal') === 'package')
                            <small>Incluye {{ $quote['included_people'] }} persona{{ $quote['included_people'] === 1 ? '' : 's' }} · extra: {{ $quote['extra_people'] }}</small>
                        @else
                            <small>{{ $result['mode'] === 'shared' ? 'Habitacion' : 'Espacio' }}: {{ money_format_decimal($quote['price_per_night']) }} Bs por noche</small>
                        @endif
                        <dl>
                            @if (($quote['booking_type'] ?? 'normal') === 'package')
                                <div><dt>Precio base paquete</dt><dd>{{ money_format_decimal($quote['package_price']) }} Bs</dd></div>
                                <div><dt>Total personas extra</dt><dd>{{ money_format_decimal($quote['extra_people_total']) }} Bs</dd></div>
                            @endif
                            <div><dt>Reserva con adelanto</dt><dd>{{ money_format_decimal($quote['advance_amount']) }} Bs</dd></div>
                            <div><dt>Saldo</dt><dd>{{ money_format_decimal($quote['balance_amount']) }} Bs</dd></div>
                        </dl>
                        <p>Tu reserva se confirma despues de validar el pago.</p>
                    </div>
                @elseif ($result['price_from'] !== null)
                    <div class="public-live-quote" id="public-estimate">
                        <span>Estimacion</span>
                        <strong>Completa fechas</strong>
                        <small>Se validara con la disponibilidad real antes de confirmar.</small>
                    </div>
                @endif

                @if (filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null))
                    @if ($result['mode'] === 'private')
                        <a class="btn btn-dark w-100" href="{{ route('public.reservations.start', ['space_id' => $space->id, ...$reservationQuery]) }}">
                            {{ $selectedPackage ? 'Reservar este paquete' : 'Reservar con adelanto' }}
                        </a>
                    @else
                        <p class="public-payment-note">Elige habitaciones completas o camas disponibles para continuar.</p>
                    @endif
                @else
                    <a class="btn btn-dark w-100" href="{{ route('public.accommodations.search', ['space_id' => $space->id, ...$result['query']]) }}">
                        Consultar fechas
                    </a>
                @endif
            </aside>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('.public-booking-form');
            const estimate = document.getElementById('public-estimate');

            const format = (amount) => new Intl.NumberFormat('es-BO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount);

            if (form && estimate) {
                const price = Number(form.dataset.price || 0);
                const bookingType = form.dataset.bookingType || 'normal';
                const includedPeople = Number(form.dataset.includedPeople || 0);
                const extraPersonPrice = Number(form.dataset.extraPersonPrice || 0);
                const checkIn = form.querySelector('[name="check_in"]');
                const checkOut = form.querySelector('[name="check_out"]');
                const guests = form.querySelector('[name="guests"]');

                const update = () => {
                    const start = checkIn.value ? new Date(checkIn.value + 'T00:00:00') : null;
                    const end = checkOut.value ? new Date(checkOut.value + 'T00:00:00') : null;
                    const people = Math.max(Number(guests.value || 1), 1);

                    if (!start || !end || end <= start || price <= 0) {
                        estimate.querySelector('strong').textContent = 'Completa fechas';
                        return;
                    }

                    const nights = Math.round((end - start) / 86400000);
                    const extraPeople = bookingType === 'package' ? Math.max(people - includedPeople, 0) : 0;
                    const total = bookingType === 'package' ? price + (extraPeople * extraPersonPrice) : price * nights;
                    estimate.querySelector('strong').textContent = `${format(total)} Bs`;
                    estimate.querySelector('small').textContent = bookingType === 'package'
                        ? `Precio cerrado del paquete · ${people} persona${people === 1 ? '' : 's'} · ${extraPeople} extra`
                        : `Total por ${nights} noche${nights === 1 ? '' : 's'} · ${people} persona${people === 1 ? '' : 's'} dentro de la capacidad · desde ${format(price)} Bs por noche`;
                };

                [checkIn, checkOut, guests].forEach((input) => input.addEventListener('input', update));
                update();
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
