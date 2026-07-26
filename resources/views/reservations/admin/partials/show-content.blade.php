@php
    $statusLabels = [
        'pending_payment' => 'Pendiente de pago',
        'payment_under_review' => 'Pago en revision',
        'confirmed' => 'Confirmada',
        'checked_in' => 'En check-in',
        'rejected' => 'Rechazada',
        'cancelled' => 'Cancelada',
        'expired' => 'Vencida',
        'no_show' => 'No show',
    ];
    $statusTones = [
        'pending_payment' => 'warning',
        'payment_under_review' => 'info',
        'confirmed' => 'success',
        'checked_in' => 'primary',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'expired' => 'secondary',
        'no_show' => 'danger',
    ];
@endphp

<div class="reservation-admin-grid">
    <x-ui.card title="Detalle de reserva">
        <div class="card-body">
            <dl class="reservation-admin-dl">
                <div><dt>Codigo</dt><dd>{{ $reservation->code }}</dd></div>
                <div><dt>Estado</dt><dd><span class="badge bg-{{ $statusTones[$reservation->status] ?? 'secondary' }}-lt">{{ $statusLabels[$reservation->status] ?? $reservation->status }}</span></dd></div>
                <div><dt>Pago</dt><dd>{{ str($reservation->payment_status)->replace('_', ' ')->headline() }}</dd></div>
                <div><dt>Canal</dt><dd>{{ $reservation->reservationChannel?->name ?: 'Sin canal' }}</dd></div>
                <div><dt>Huesped</dt><dd>{{ $reservation->guest_name }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $reservation->guest_email ?: 'Sin correo' }}</dd></div>
                <div><dt>Telefono</dt><dd>{{ $reservation->guest_phone ?: 'Sin telefono' }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $reservation->guest_document ?: 'Sin documento' }}</dd></div>
                <div><dt>Alojamiento</dt><dd>{{ $reservation->space->title ?: $reservation->space->name }}</dd></div>
                <div><dt>Recurso</dt><dd>{{ $reservation->room ? ($reservation->room->title ?: $reservation->room->name) : 'Espacio completo' }}</dd></div>
                <div><dt>Fechas</dt><dd>{{ $reservation->check_in->format('d/m/Y') }} al {{ $reservation->check_out->format('d/m/Y') }}</dd></div>
                <div><dt>Personas</dt><dd>{{ $reservation->guests }}</dd></div>
            </dl>
        </div>
    </x-ui.card>

    <x-ui.card title="Resumen de pago">
        <div class="card-body">
            <dl class="reservation-admin-dl">
                <div><dt>Precio noche</dt><dd>{{ money_format_decimal($reservation->price_per_person) }} {{ $reservation->currency }}</dd></div>
                <div><dt>Subtotal</dt><dd>{{ money_format_decimal($reservation->subtotal_amount) }} {{ $reservation->currency }}</dd></div>
                <div><dt>Total</dt><dd>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</dd></div>
                <div><dt>Adelanto</dt><dd>{{ money_format_decimal($reservation->advance_amount) }} {{ $reservation->currency }}</dd></div>
                <div><dt>Saldo</dt><dd>{{ money_format_decimal($reservation->balance_amount) }} {{ $reservation->currency }}</dd></div>
                <div><dt>Referencia</dt><dd>{{ $reservation->payment_reference ?: 'Sin referencia' }}</dd></div>
            </dl>
        </div>
    </x-ui.card>
</div>

@if ($reservation->guest_notes)
    <x-ui.card title="Notas" class="mt-3">
        <div class="card-body">
            <p class="mb-0">{{ $reservation->guest_notes }}</p>
        </div>
    </x-ui.card>
@endif
