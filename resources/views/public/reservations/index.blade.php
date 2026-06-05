@extends('layouts.public', ['title' => 'Mis reservas'])

@section('content')
    @php
        $statusLabels = [
            'pending_payment' => 'Adelanto pendiente',
            'payment_under_review' => 'Pago en revision',
            'confirmed' => 'Confirmada',
            'rejected' => 'Pago rechazado',
            'cancelled' => 'Cancelada',
            'expired' => 'Expirada',
        ];
    @endphp

    <section class="container-xl public-results">
        <div class="public-results-heading">
            <div>
                <p class="public-eyebrow mb-1">Cuenta</p>
                <h2>Mis reservas</h2>
            </div>
        </div>

        @if ($reservations->isEmpty())
            <div class="public-empty">
                <i class="ti ti-calendar-off"></i>
                <h3>Sin reservas aun</h3>
                <p>Busca un alojamiento y crea tu primera solicitud.</p>
            </div>
        @else
            <div class="public-reservation-list">
                @foreach ($reservations as $reservation)
                    <a class="public-reservation-item" href="{{ route('public.reservations.show', $reservation->id) }}">
                        <div>
                            <strong>{{ $reservation->space->title ?: $reservation->space->name }}</strong>
                            <span>
                                {{ $reservation->code }} · {{ $reservation->check_in->toDateString() }} al {{ $reservation->check_out->toDateString() }}
                                @if ($reservation->rooms->count() > 1)
                                    · {{ $reservation->rooms->count() }} habitaciones
                                @endif
                            </span>
                        </div>
                        <div>
                            <strong>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</strong>
                            <span>{{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ')->headline() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-4">{{ $reservations->links() }}</div>
        @endif
    </section>
@endsection
