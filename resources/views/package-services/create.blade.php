@extends('layouts.admin')

@section('title', 'Nuevo servicio de paquete | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo servicio de paquete')
@section('page-subtitle', 'Catalogo de servicios para paquetes todo incluido')

@section('content')
    <x-ui.form-panel title="Datos del servicio">
        <form method="post" action="{{ route('package-services.store') }}">
            @include('package-services.partials.form')
        </form>
    </x-ui.form-panel>
@endsection
