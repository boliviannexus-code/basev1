@extends('layouts.public', ['title' => 'Reserva '.$reservation->code])

@section('content')
    @php
        $statusLabels = [
            'pending_payment' => 'Reserva con adelanto pendiente',
            'payment_under_review' => 'Tu pago esta en revision',
            'confirmed' => 'Reserva confirmada',
            'rejected' => 'Pago rechazado',
            'cancelled' => 'Reserva cancelada',
            'expired' => 'Reserva expirada',
        ];
        $paymentLabels = [
            'pending' => 'Pago QR pendiente',
            'submitted' => 'Pago en revision',
            'validated' => 'Adelanto validado',
            'rejected' => 'Pago rechazado',
        ];
        $statusDescriptions = [
            'pending_payment' => 'Tu reserva se confirma despues de validar el pago. Mientras tanto puedes revisar el resumen y adjuntar el comprobante.',
            'payment_under_review' => 'Recibimos tu comprobante. El establecimiento revisara el adelanto y confirmara tu reserva.',
            'confirmed' => 'Tu adelanto fue validado y la reserva quedo confirmada para las fechas seleccionadas.',
            'rejected' => 'El establecimiento rechazo el comprobante de pago. Revisa el detalle o contacta al alojamiento.',
            'cancelled' => 'Esta reserva fue cancelada.',
            'expired' => 'El bloqueo temporal expiro antes de validar el adelanto.',
        ];
    @endphp
    @php($packageSnapshot = $reservation->package_snapshot ?? [])

    <section class="container-xl public-reservation">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        <div class="public-reservation-grid">
            <article class="public-reservation-form">
                <section>
                    <p class="public-eyebrow">Reserva {{ $reservation->code }}</p>
                    <h1>{{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ')->headline() }}</h1>
                    <p class="text-body-secondary mb-0">{{ $statusDescriptions[$reservation->status] ?? 'Revisa el resumen de tu reserva.' }}</p>
                    @if ($reservation->hold_expires_at && $reservation->status === 'pending_payment')
                        <div class="alert alert-warning mt-3 mb-0">
                            Bloqueo temporal activo hasta {{ $reservation->hold_expires_at->format('d/m/Y H:i') }}.
                        </div>
                    @elseif ($reservation->status === 'confirmed' && $reservation->payment_validated_at)
                        <div class="alert alert-success mt-3 mb-0">
                            Adelanto validado el {{ $reservation->payment_validated_at->format('d/m/Y H:i') }}.
                        </div>
                    @endif
                </section>

                <section class="public-qr-placeholder">
                    <i class="ti {{ $reservation->payment_status === 'validated' ? 'ti-circle-check' : 'ti-qrcode' }}"></i>
                    <div>
                        <h2>{{ $paymentLabels[$reservation->payment_status] ?? str($reservation->payment_status)->replace('_', ' ')->headline() }}</h2>
                        <p>
                            @if ($reservation->payment_status === 'validated')
                                El establecimiento valido el adelanto y bloqueo la disponibilidad de forma definitiva.
                            @else
                                La generacion dinamica del QR quedo preparada para la siguiente etapa. Por ahora adjunta tu comprobante si ya realizaste el adelanto.
                            @endif
                        </p>
                    </div>
                </section>

                @if ($reservation->canSubmitPaymentProof())
                    <section>
                        <h2>Comprobante de adelanto</h2>
                        <form class="public-payment-proof-form" action="{{ route('public.reservations.payment-proof', $reservation->id) }}" method="post" enctype="multipart/form-data">
                            @csrf
                            <div>
                                <label class="form-label" for="payment_reference">Referencia</label>
                                <input class="form-control @error('payment_reference') is-invalid @enderror" id="payment_reference" name="payment_reference" value="{{ old('payment_reference', $reservation->payment_reference) }}" placeholder="Numero de transaccion o nota">
                                @error('payment_reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div>
                                <label class="form-label" for="payment_proof">Comprobante</label>
                                <input class="form-control @error('payment_proof') is-invalid @enderror" id="payment_proof" name="payment_proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                                @error('payment_proof')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button class="btn btn-dark" type="submit">
                                <i class="ti ti-upload"></i>
                                Enviar comprobante
                            </button>
                        </form>
                    </section>
                @endif

                @if ($reservation->payment_proof_path)
                    <section>
                        <h2>Comprobante enviado</h2>
                        <a class="btn btn-outline-dark" href="{{ Storage::disk('public')->url($reservation->payment_proof_path) }}" target="_blank" rel="noopener">
                            <i class="ti ti-file-search"></i>
                            Ver comprobante
                        </a>
                    </section>
                @endif

                <section>
                    <h2>Datos registrados</h2>
                    <dl class="public-reservation-dl">
                        <div><dt>Responsable</dt><dd>{{ $reservation->guest_name }}</dd></div>
                        <div><dt>Correo</dt><dd>{{ $reservation->guest_email }}</dd></div>
                        <div><dt>Telefono</dt><dd>{{ $reservation->guest_phone ?: 'Sin telefono' }}</dd></div>
                        <div><dt>Pais</dt><dd>{{ $reservation->guest_country ?: 'Sin pais' }}</dd></div>
                        <div><dt>Documento</dt><dd>{{ $reservation->guest_document ?: 'Sin documento' }}</dd></div>
                        <div><dt>Estado</dt><dd>{{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ')->headline() }}</dd></div>
                        <div><dt>Pago</dt><dd>{{ $paymentLabels[$reservation->payment_status] ?? str($reservation->payment_status)->replace('_', ' ')->headline() }}</dd></div>
                    </dl>
                </section>

                @if ($reservation->canBeEditedByGuest())
                    <a class="btn btn-outline-dark" href="{{ route('public.reservations.edit', $reservation->id) }}">
                        <i class="ti ti-pencil"></i>
                        Editar datos
                    </a>
                @endif
            </article>

            <aside class="public-booking-panel public-reservation-summary">
                <h2>Resumen</h2>
                <strong>{{ $reservation->space->title ?: $reservation->space->name }}</strong>
                @if ($reservation->booking_type === 'package')
                    <p class="mb-2">Paquete: {{ $packageSnapshot['name'] ?? $reservation->accommodationPackage?->name ?? 'Paquete todo incluido' }}</p>
                    @if (! empty($packageSnapshot['services']))
                        <div class="public-chip-list mb-2">
                            @foreach (collect($packageSnapshot['services'])->where('inclusion_type', 'included')->take(6) as $service)
                                <span>{{ $service['name'] }}</span>
                            @endforeach
                        </div>
                    @endif
                @elseif ($reservation->rooms->count() > 1)
                    <p class="mb-2">{{ $reservation->rooms->count() }} habitaciones seleccionadas</p>
                    <div class="public-chip-list mb-2">
                        @foreach ($reservation->rooms as $selectedRoom)
                            <span>{{ $selectedRoom->title ?: $selectedRoom->name }}</span>
                        @endforeach
                    </div>
                @elseif ($reservation->bedUnitItems->isNotEmpty())
                    <p class="mb-2">{{ $reservation->bedUnitItems->count() }} cama{{ $reservation->bedUnitItems->count() === 1 ? '' : 's' }} seleccionada{{ $reservation->bedUnitItems->count() === 1 ? '' : 's' }}</p>
                    <div class="public-chip-list mb-2">
                        @foreach ($reservation->bedUnitItems as $item)
                            <span>{{ $item->bedUnit?->room?->title ?: $item->bedUnit?->room?->name }} · {{ $item->bedUnit?->label }}</span>
                        @endforeach
                    </div>
                @elseif ($reservation->rooms->count() === 1)
                    <p class="mb-2">{{ $reservation->rooms->first()->title ?: $reservation->rooms->first()->name }}</p>
                @elseif ($reservation->room)
                    <p class="mb-2">{{ $reservation->room->title ?: $reservation->room->name }}</p>
                @endif
                <dl>
                    <div><dt>Ingreso</dt><dd>{{ $reservation->check_in->toDateString() }}</dd></div>
                    <div><dt>Salida</dt><dd>{{ $reservation->check_out->toDateString() }}</dd></div>
                    <div><dt>Noches</dt><dd>{{ $reservation->nights }}</dd></div>
                    <div><dt>Personas</dt><dd>{{ $reservation->guests }}</dd></div>
                    @if ($reservation->booking_type === 'package')
                        <div><dt>Precio base paquete</dt><dd>{{ money_format_decimal($reservation->package_price) }} {{ $reservation->currency }}</dd></div>
                        <div><dt>Personas incluidas</dt><dd>{{ $reservation->included_people }}</dd></div>
                        <div><dt>Personas extra</dt><dd>{{ $reservation->extra_people }}</dd></div>
                        <div><dt>Total personas extra</dt><dd>{{ money_format_decimal($reservation->extra_people_total) }} {{ $reservation->currency }}</dd></div>
                        @if ((int) $reservation->package_extra_nights > 0)
                            <div><dt>Noches extra preparadas</dt><dd>{{ $reservation->package_extra_nights }} · {{ money_format_decimal($reservation->package_extra_nights_total) }} {{ $reservation->currency }}</dd></div>
                        @endif
                        <div><dt>Total final del paquete</dt><dd>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</dd></div>
                    @else
                        <div><dt>Total por {{ $reservation->nights }} noche{{ $reservation->nights === 1 ? '' : 's' }}</dt><dd>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</dd></div>
                    @endif
                    <div><dt>Reserva con adelanto</dt><dd>{{ money_format_decimal($reservation->advance_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Saldo pendiente</dt><dd>{{ money_format_decimal($reservation->balance_amount) }} {{ $reservation->currency }}</dd></div>
                </dl>
                <a class="btn btn-outline-dark w-100" href="{{ route('public.reservations.index') }}">Ver mis reservas</a>
            </aside>
        </div>
    </section>
@endsection
