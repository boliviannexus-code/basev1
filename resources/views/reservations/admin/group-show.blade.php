@extends('layouts.admin')

@section('title', 'Reserva '.$group->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reserva '.$group->code)
@section('page-subtitle', 'Vista informativa de reserva interna')

@section('content')
    @include('reservations.admin.partials.group-show-content')
@endsection
