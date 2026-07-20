@extends('layouts.admin')

@section('title', 'Nueva cancha | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva cancha')
@section('page-subtitle', 'Registra una cancha para la liga')

@section('content')
    <x-ui.form-panel title="Datos de la cancha">
        @include('courts.partials.create-form')
    </x-ui.form-panel>
@endsection
