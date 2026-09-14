@extends('layouts.admin')
@section('title', 'Editar ítem | '.config('app.name'))
@section('page-title', 'Editar ítem de derecho de cancha')
@section('page-subtitle', $courtFeeItem->name)
@section('content')
    <x-ui.form-panel title="Datos del ítem">@include('court-fee-items.partials.edit-form')</x-ui.form-panel>
@endsection
