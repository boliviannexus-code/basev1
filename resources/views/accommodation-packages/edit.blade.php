@extends('layouts.admin')

@section('title', 'Editar paquete todo incluido | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar paquete todo incluido')
@section('page-subtitle', $package->name)

@section('content')
    <x-ui.form-panel class="package-form-panel" title="Datos del paquete">
        <form method="post" action="{{ route('accommodation-packages.update', $package) }}" enctype="multipart/form-data">
            @method('put')
            @include('accommodation-packages.partials.form')
        </form>
    </x-ui.form-panel>
@endsection
