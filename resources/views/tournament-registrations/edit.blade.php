@extends('layouts.admin')

@section('title', 'Editar inscripcion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar inscripcion')

@section('content')
    <x-ui.form-panel title="Datos de la inscripcion">
        @include('tournament-registrations.partials.edit-form')
    </x-ui.form-panel>
@endsection
