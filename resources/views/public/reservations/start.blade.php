@extends('layouts.public', ['title' => 'Confirmar reserva'])

@section('content')
    @php($space = $quote['space'])
    @php($room = $quote['room'])
    @php($rooms = $quote['rooms'] ?? collect())
    @php($roomItems = $quote['room_items'] ?? collect())
    @php($user = auth()->user())

    <section class="container-xl public-reservation">
        <a class="public-back-link" href="{{ route('public.accommodations.show', ['space' => $space, ...collect($filters)->except(['space_room_id', 'space_room_ids'])->all()]) }}">
            <i class="ti ti-arrow-left"></i>
            Volver al alojamiento
        </a>

        <div class="public-reservation-grid">
            <form class="public-reservation-form" action="{{ route('public.reservations.store') }}" method="post">
                @csrf
                <input type="hidden" name="space_id" value="{{ $space->id }}">
                @if ($rooms->isNotEmpty())
                    @foreach ($rooms as $selectedRoom)
                        <input type="hidden" name="space_room_ids[]" value="{{ $selectedRoom->id }}">
                    @endforeach
                @else
                    <input type="hidden" name="space_room_id" value="{{ $room?->id }}">
                @endif
                <input type="hidden" name="check_in" value="{{ $quote['check_in'] }}">
                <input type="hidden" name="check_out" value="{{ $quote['check_out'] }}">
                <input type="hidden" name="guests" value="{{ $quote['guests'] }}">

                <section>
                    <p class="public-eyebrow">Solicitud de reserva</p>
                    <h1>Confirma tus datos y reserva con adelanto</h1>
                    <p class="text-body-secondary mb-0">Ya calculaste el total. Solo necesitamos tus datos para preparar la solicitud y pasar al pago QR.</p>
                    <div class="public-step-list" aria-label="Proceso de reserva">
                        <span class="is-done">Seleccion</span>
                        <span class="is-current">Datos</span>
                        <span>Pago QR</span>
                    </div>
                </section>

                <section>
                    <h2>Seleccion</h2>
                    <div class="public-selection-card">
                        <div>
                            <strong>{{ $space->title ?: $space->name }}</strong>
                            @if ($rooms->count() > 1)
                                <span>{{ $rooms->count() }} habitaciones seleccionadas</span>
                            @elseif ($rooms->count() === 1)
                                <span>{{ $rooms->first()->title ?: $rooms->first()->name }}</span>
                            @else
                                <span>Espacio completo privado</span>
                            @endif
                        </div>
                        <span>{{ $quote['capacity'] }} persona{{ $quote['capacity'] === 1 ? '' : 's' }} de capacidad</span>
                    </div>
                    @if ($roomItems->isNotEmpty())
                        <div class="public-chip-list mt-3">
                            @foreach ($roomItems as $item)
                                <span>{{ $item['room']->title ?: $item['room']->name }} · {{ $item['capacity'] }} persona{{ $item['capacity'] === 1 ? '' : 's' }} · Bs {{ money_format_decimal($item['price_per_night']) }}/noche</span>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section>
                    <h2>Datos de la persona responsable</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="guest_name">Nombre completo</label>
                            <input class="form-control @error('guest_name') is-invalid @enderror" id="guest_name" name="guest_name" value="{{ old('guest_name', $user?->name) }}" required>
                            @error('guest_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_email">Correo</label>
                            <input class="form-control @error('guest_email') is-invalid @enderror" id="guest_email" name="guest_email" type="email" value="{{ old('guest_email', $user?->email) }}" required>
                            @error('guest_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_phone">Telefono</label>
                            <input class="form-control @error('guest_phone') is-invalid @enderror" id="guest_phone" name="guest_phone" value="{{ old('guest_phone') }}" placeholder="+591">
                            @error('guest_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_country">Pais</label>
                            <input class="form-control @error('guest_country') is-invalid @enderror" id="guest_country" name="guest_country" value="{{ old('guest_country', 'Bolivia') }}">
                            @error('guest_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_document">Documento</label>
                            <input class="form-control @error('guest_document') is-invalid @enderror" id="guest_document" name="guest_document" value="{{ old('guest_document') }}">
                            @error('guest_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                @guest
                    <section>
                        <h2>Acceso para guardar tu reserva</h2>
                        <p class="text-body-secondary mb-2">Te pedimos cuenta recien ahora para que puedas ver el estado del pago y recibir seguimiento.</p>
                        <div class="public-account-options">
                            <label>
                                <input type="radio" name="account_mode" value="register" @checked(old('account_mode', 'register') === 'register')>
                                <span>Crear cuenta</span>
                            </label>
                            <label>
                                <input type="radio" name="account_mode" value="login" @checked(old('account_mode') === 'login')>
                                <span>Iniciar sesion</span>
                            </label>
                        </div>
                        @error('account_mode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="password">Contrasena</label>
                                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="password_confirmation">Confirmar contrasena</label>
                                <input class="form-control @error('password_confirmation') is-invalid @enderror" id="password_confirmation" name="password_confirmation" type="password">
                                @error('password_confirmation')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </section>
                @else
                    <input type="hidden" name="account_mode" value="current">
                @endguest

                <section>
                    <h2>Notas para el establecimiento</h2>
                    <textarea class="form-control @error('guest_notes') is-invalid @enderror" name="guest_notes" rows="3" placeholder="Hora aproximada de llegada, preferencias o dudas">{{ old('guest_notes') }}</textarea>
                    @error('guest_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </section>

                <button class="btn btn-dark public-reservation-submit" type="submit">
                    <i class="ti ti-qrcode"></i>
                    Confirmar y continuar al pago
                </button>
            </form>

            <aside class="public-booking-panel public-reservation-summary">
                <h2>Resumen</h2>
                <strong>{{ $space->title ?: $space->name }}</strong>
                @if ($rooms->count() > 1)
                    <p class="mb-2">{{ $rooms->count() }} habitaciones seleccionadas</p>
                @elseif ($rooms->count() === 1)
                    <p class="mb-2">{{ $rooms->first()->title ?: $rooms->first()->name }}</p>
                @elseif ($room)
                    <p class="mb-2">{{ $room->title ?: $room->name }}</p>
                @endif
                <dl>
                    <div>
                        <dt>Ingreso</dt>
                        <dd>{{ $quote['check_in'] }}</dd>
                    </div>
                    <div>
                        <dt>Salida</dt>
                        <dd>{{ $quote['check_out'] }}</dd>
                    </div>
                    <div>
                        <dt>Noches</dt>
                        <dd>{{ $quote['nights'] }}</dd>
                    </div>
                    <div>
                        <dt>Personas</dt>
                        <dd>{{ $quote['guests'] }}</dd>
                    </div>
                    <div>
                        <dt>{{ $rooms->count() > 1 ? 'Total habitaciones por noche' : 'Precio por noche' }}</dt>
                        <dd>{{ money_format_decimal($quote['price_per_night']) }} BOB</dd>
                    </div>
                    <div>
                        <dt>Total por {{ $quote['nights'] }} noche{{ $quote['nights'] === 1 ? '' : 's' }}</dt>
                        <dd>{{ money_format_decimal($quote['total_amount']) }} BOB</dd>
                    </div>
                    <div>
                        <dt>Reserva con adelanto</dt>
                        <dd>{{ money_format_decimal($quote['advance_amount']) }} BOB</dd>
                    </div>
                    <div>
                        <dt>Saldo pendiente</dt>
                        <dd>{{ money_format_decimal($quote['balance_amount']) }} BOB</dd>
                    </div>
                </dl>
                <p class="public-payment-note">Tu reserva se confirma despues de validar el pago.</p>
            </aside>
        </div>
    </section>
@endsection
