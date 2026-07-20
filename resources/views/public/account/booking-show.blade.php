@extends('layouts.public')

@section('title', 'Reserva '.$booking->booking_code)

@section('content')
<section class="account-shell">
    <div class="container-xl detail-layout">
        <article class="detail-content">
            <a class="public-back" href="{{ route('tourist.reservations.index') }}"><i class="ti ti-arrow-left"></i>Mis reservas</a>
            <h1>Reserva {{ $booking->booking_code }}</h1>
            <x-public.booking-status :status="$booking->status" />
            <div class="quick-facts mt-4">
                <div><i class="ti ti-calendar"></i><strong>Fecha</strong><span>{{ $booking->travel_date->translatedFormat('d M Y') }}</span></div>
                <div><i class="ti ti-users"></i><strong>Personas</strong><span>{{ $booking->people }}</span></div>
                <div><i class="ti ti-cash"></i><strong>Total</strong><span>${{ number_format((float) $booking->total_usd, 2) }}</span></div>
            </div>
            <h2>{{ $booking->tour->display_title }}</h2>
            <p>{{ $booking->tour->short_description ?: $booking->tour->description }}</p>
            <h2>Datos del turista</h2>
            <p>{{ $booking->first_name }} {{ $booking->last_name }} · {{ $booking->email }} · {{ $booking->phone }} · {{ $booking->country }}</p>
            @if ($booking->special_requirements)
                <h2>Requerimientos especiales</h2>
                <p>{{ $booking->special_requirements }}</p>
            @endif
        </article>
        <aside class="booking-box">
            <h2>Voucher</h2>
            <p class="text-muted">Presenta este comprobante el dia del tour.</p>
            <a class="btn btn-primary w-100" href="{{ route('tourist.reservations.voucher', $booking) }}">Descargar voucher</a>
        </aside>
    </div>
</section>
@endsection
