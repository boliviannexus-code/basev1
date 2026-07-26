@extends('layouts.admin')

@section('title', 'Modificar reserva '.$group->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Modificar reserva '.$group->code)
@section('page-subtitle', 'Edicion operativa con validacion de disponibilidad')

@section('content')
    @include('reservations.admin.partials.group-edit-form')
@endsection
