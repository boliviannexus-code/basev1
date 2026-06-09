@extends('layouts.public', ['title' => 'Editar reserva'])

@section('content')
    <section class="container-xl public-reservation">
        <a class="public-back-link" href="{{ route('public.reservations.show', $reservation->id) }}">
            <i class="ti ti-arrow-left"></i>
            Volver a la reserva
        </a>

        <div class="public-reservation-grid">
            <form class="public-reservation-form" action="{{ route('public.reservations.update', $reservation->id) }}" method="post">
                @csrf
                @method('put')

                <section>
                    <p class="public-eyebrow">Reserva {{ $reservation->code }}</p>
                    <h1>Editar datos</h1>
                    <p class="text-body-secondary mb-0">Puedes ajustar tus datos mientras la solicitud siga pendiente de pago.</p>
                </section>

                <section>
                    <h2>Datos de la persona responsable</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="guest_name">Nombre completo</label>
                            <input class="form-control @error('guest_name') is-invalid @enderror" id="guest_name" name="guest_name" value="{{ old('guest_name', $reservation->guest_name) }}" required>
                            @error('guest_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_email">Correo</label>
                            <input class="form-control @error('guest_email') is-invalid @enderror" id="guest_email" name="guest_email" type="email" value="{{ old('guest_email', $reservation->guest_email) }}" required>
                            @error('guest_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_phone">Telefono</label>
                            <input class="form-control @error('guest_phone') is-invalid @enderror" id="guest_phone" name="guest_phone" value="{{ old('guest_phone', $reservation->guest_phone) }}">
                            @error('guest_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_country">Pais</label>
                            <input class="form-control @error('guest_country') is-invalid @enderror" id="guest_country" name="guest_country" value="{{ old('guest_country', $reservation->guest_country) }}">
                            @error('guest_country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="guest_document">Documento</label>
                            <input class="form-control @error('guest_document') is-invalid @enderror" id="guest_document" name="guest_document" value="{{ old('guest_document', $reservation->guest_document) }}">
                            @error('guest_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section>
                    <h2>Notas para el establecimiento</h2>
                    <textarea class="form-control @error('guest_notes') is-invalid @enderror" name="guest_notes" rows="3">{{ old('guest_notes', $reservation->guest_notes) }}</textarea>
                    @error('guest_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </section>

                <button class="btn btn-dark public-reservation-submit" type="submit">
                    <i class="ti ti-device-floppy"></i>
                    Guardar cambios
                </button>
            </form>

            <aside class="public-booking-panel public-reservation-summary">
                <h2>Resumen fijo</h2>
                <strong>{{ $reservation->space->title ?: $reservation->space->name }}</strong>
                @if ($reservation->bedUnitItems->isNotEmpty())
                    <p class="mb-2">{{ $reservation->bedUnitItems->count() }} cama{{ $reservation->bedUnitItems->count() === 1 ? '' : 's' }} seleccionada{{ $reservation->bedUnitItems->count() === 1 ? '' : 's' }}</p>
                    <div class="public-chip-list mb-2">
                        @foreach ($reservation->bedUnitItems as $item)
                            <span>{{ $item->bedUnit?->room?->title ?: $item->bedUnit?->room?->name }} · {{ $item->bedUnit?->label }}</span>
                        @endforeach
                    </div>
                @elseif ($reservation->rooms->count() > 1)
                    <p class="mb-2">{{ $reservation->rooms->count() }} habitaciones seleccionadas</p>
                @elseif ($reservation->rooms->count() === 1)
                    <p class="mb-2">{{ $reservation->rooms->first()->title ?: $reservation->rooms->first()->name }}</p>
                @elseif ($reservation->room)
                    <p class="mb-2">{{ $reservation->room->title ?: $reservation->room->name }}</p>
                @endif
                <dl>
                    <div><dt>Ingreso</dt><dd>{{ $reservation->check_in->toDateString() }}</dd></div>
                    <div><dt>Salida</dt><dd>{{ $reservation->check_out->toDateString() }}</dd></div>
                    <div><dt>Total</dt><dd>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Adelanto QR</dt><dd>{{ money_format_decimal($reservation->advance_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Saldo pendiente</dt><dd>{{ money_format_decimal($reservation->balance_amount) }} {{ $reservation->currency }}</dd></div>
                </dl>
            </aside>
        </div>
    </section>
@endsection
