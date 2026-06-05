@extends('layouts.public', ['title' => $result['title']])

@section('content')
    @php($space = $result['space'])
    @php($photos = $space->photos)
    @php($selectedGuests = (int) ($filters['guests'] ?? 1))
    @php($selectedCheckIn = $filters['check_in'] ?? now()->toDateString())
    @php($selectedCheckOut = $filters['check_out'] ?? now()->addDay()->toDateString())
    @php($sharedSelectionEnabled = $result['mode'] === 'shared' && filled($filters['check_in'] ?? null) && filled($filters['check_out'] ?? null))

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
                                <label class="public-room-row" data-shared-room-option data-capacity="{{ $roomCapacity }}" data-price="{{ $roomPrice }}" data-subtotal="{{ $roomSubtotal }}">
                                    <span>
                                        <strong>{{ $room->title ?: $room->name }}</strong>
                                        @if ($room->description)
                                            <p>{{ $room->description }}</p>
                                        @endif
                                        @if ($roomQuote)
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
                                        @if ($sharedSelectionEnabled && $roomQuote)
                                            <input class="form-check-input" type="checkbox" name="space_room_ids[]" value="{{ $room->id }}" data-shared-room-checkbox>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                            @if ($sharedSelectionEnabled)
                                <div class="public-live-quote" data-shared-room-summary>
                                    <span data-shared-room-summary-count>Selecciona una o mas habitaciones</span>
                                    <strong data-shared-room-summary-total>Bs 0.00</strong>
                                    <small data-shared-room-summary-detail>Capacidad seleccionada: 0 de {{ $selectedGuests }} persona{{ $selectedGuests === 1 ? '' : 's' }}</small>
                                    <button class="btn btn-dark w-100" type="submit" data-shared-room-submit disabled>
                                        Reservar habitaciones seleccionadas
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

            <aside class="public-booking-panel">
                <h2>Calcula tu estadia</h2>
                <p>{{ $result['availability_note'] }} Explora el total antes de registrarte.</p>

                <form class="public-booking-form" action="{{ route('public.accommodations.show', ['space' => $space]) }}" method="get" data-price="{{ $result['price_from'] ?? 0 }}">
                    @if (filled($filters['destination'] ?? null))
                        <input type="hidden" name="destination" value="{{ $filters['destination'] }}">
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
                        <span>Total por {{ $quote['nights'] }} noche{{ $quote['nights'] === 1 ? '' : 's' }}</span>
                        <strong>{{ money_format_decimal($quote['total_amount']) }} Bs</strong>
                        <small>{{ $result['mode'] === 'shared' ? 'Habitacion' : 'Espacio' }}: {{ money_format_decimal($quote['price_per_night']) }} Bs por noche</small>
                        <dl>
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
                        <a class="btn btn-dark w-100" href="{{ route('public.reservations.start', ['space_id' => $space->id, ...$result['query']]) }}">
                            Reservar con adelanto
                        </a>
                    @else
                        <p class="public-payment-note">Elige una o mas habitaciones disponibles para continuar.</p>
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
                    const total = price * nights;
                    estimate.querySelector('strong').textContent = `${format(total)} Bs`;
                    estimate.querySelector('small').textContent = `Total por ${nights} noche${nights === 1 ? '' : 's'} · ${people} persona${people === 1 ? '' : 's'} dentro de la capacidad · desde ${format(price)} Bs por noche`;
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
                const capacity = selected.reduce((sum, option) => sum + Number(option.dataset.capacity || 0), 0);
                const amount = selected.reduce((sum, option) => sum + Number(option.dataset.subtotal || 0), 0);
                const perNight = selected.reduce((sum, option) => sum + Number(option.dataset.price || 0), 0);
                const valid = selected.length > 0 && capacity >= requiredGuests;

                count.textContent = selected.length === 1 ? '1 habitacion seleccionada' : `${selected.length} habitaciones seleccionadas`;
                total.textContent = `Bs ${format(amount)}`;
                detail.textContent = `Capacidad seleccionada: ${capacity} de ${requiredGuests} persona${requiredGuests === 1 ? '' : 's'} · Bs ${format(perNight)} por noche`;
                submit.disabled = !valid;
            };

            checkboxes.forEach((checkbox) => checkbox.addEventListener('change', updateRooms));
            updateRooms();
        });
    </script>
@endsection
