@extends('layouts.admin')
@section('title', 'Nuevo derecho de cancha | '.config('app.name'))
@section('page-title', 'Nuevo derecho de cancha')
@section('page-subtitle', 'Crea una tarifa y luego agrega sus ítems')
@section('content')
<x-ui.form-panel title="Datos generales">@include('court-fees.partials.create-form')</x-ui.form-panel>
@endsection
