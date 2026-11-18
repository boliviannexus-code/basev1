@extends('layouts.admin')

@section('title', 'Reserva '.$reservation->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reserva '.$reservation->code)
@section('page-subtitle', 'Revision de solicitud publica y pago QR')

@section('content')
    @include('reservations.admin.partials.show-content')
@endsection
