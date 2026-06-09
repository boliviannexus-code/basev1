@extends('layouts.admin')

@section('title', 'Nuevo paquete todo incluido | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo paquete todo incluido')
@section('page-subtitle', 'Configuracion comercial del paquete')

@section('content')
    <x-ui.form-panel class="package-form-panel" title="Datos del paquete">
        <form method="post" action="{{ route('accommodation-packages.store') }}" enctype="multipart/form-data">
            @include('accommodation-packages.partials.form')
        </form>
    </x-ui.form-panel>
@endsection
