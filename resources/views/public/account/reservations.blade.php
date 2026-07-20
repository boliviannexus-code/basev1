@extends('layouts.public')

@section('title', 'Mis reservas')

@section('content')
<section class="account-shell">
    <div class="container-xl">
        @include('public.account.partials.nav')
        <div class="account-header">
            <h1>Mis reservas</h1>
            <a class="btn btn-primary" href="{{ route('public.tours.index') }}">Buscar mas tours</a>
        </div>
        <div class="booking-list">
            @forelse ($bookings as $booking)
                <article class="booking-row">
                    <div>
                        <strong>{{ $booking->tour->display_title }}</strong>
                        <span>{{ $booking->travel_date->translatedFormat('d M Y') }} · {{ $booking->people }} personas · {{ $booking->booking_code }}</span>
                    </div>
                    <x-public.booking-status :status="$booking->status" />
                    <strong>${{ number_format((float) $booking->total_usd, 2) }}</strong>
                    <a class="btn btn-outline-primary btn-sm" href="{{ route('tourist.reservations.show', $booking) }}">Ver detalle</a>
                </article>
            @empty
                <div class="public-empty">Aun no tienes reservas registradas.</div>
            @endforelse
        </div>
        <div class="mt-4">{{ $bookings->links() }}</div>
    </div>
</section>
@endsection
