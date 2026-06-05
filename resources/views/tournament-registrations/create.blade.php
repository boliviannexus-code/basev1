@extends('layouts.admin')

@section('title', 'Nueva inscripcion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva inscripcion')

@section('content')
    <x-ui.form-panel title="Datos de la inscripcion">
        @include('tournament-registrations.partials.create-form')
    </x-ui.form-panel>
@endsection
