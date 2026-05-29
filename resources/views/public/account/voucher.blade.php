@extends('layouts.public')

@section('title', 'Voucher '.$booking->booking_code)

@section('content')
<section class="voucher-page">
    <div class="voucher-card">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <span class="eyebrow">Voucher de reserva</span>
                <h1>{{ $booking->booking_code }}</h1>
            </div>
            <x-public.booking-status :status="$booking->status" />
        </div>
        <hr>
        <h2>{{ $booking->tour->display_title }}</h2>
        <div class="voucher-grid">
            <div><span>Fecha</span><strong>{{ $booking->travel_date->translatedFormat('d M Y') }}</strong></div>
            <div><span>Personas</span><strong>{{ $booking->people }}</strong></div>
            <div><span>Titular</span><strong>{{ $booking->first_name }} {{ $booking->last_name }}</strong></div>
            <div><span>Total</span><strong>${{ number_format((float) $booking->total_usd, 2) }}</strong></div>
            <div><span>Punto de encuentro</span><strong>{{ $booking->tour->meeting_point ?: 'A coordinar' }}</strong></div>
            <div><span>Operador</span><strong>{{ $booking->tour->company->name ?? config('app.name') }}</strong></div>
        </div>
        <button class="btn btn-primary mt-4 d-print-none" onclick="window.print()" type="button"><i class="ti ti-download me-2"></i>Descargar voucher</button>
    </div>
</section>
@endsection
