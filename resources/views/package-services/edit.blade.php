@extends('layouts.admin')

@section('title', 'Editar servicio de paquete | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar servicio de paquete')
@section('page-subtitle', $service->name)

@section('content')
    <x-ui.form-panel title="Datos del servicio">
        <form method="post" action="{{ route('package-services.update', $service) }}">
            @method('put')
            @include('package-services.partials.form')
        </form>
    </x-ui.form-panel>
@endsection
