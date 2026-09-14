@extends('layouts.admin')
@section('title', 'Nuevo ítem | '.config('app.name'))
@section('page-title', 'Nuevo ítem de derecho de cancha')
@section('page-subtitle', $courtFee->name)
@section('content')
    <x-ui.form-panel title="Datos del ítem">@include('court-fee-items.partials.create-form')</x-ui.form-panel>
@endsection
