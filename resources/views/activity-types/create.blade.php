@extends('layouts.admin')

@section('title', 'Nuevo tipo de actividad | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo tipo de actividad')

@section('content')
    <x-ui.form-panel title="Datos del tipo de actividad">
        @include('activity-types.partials.create-form')
    </x-ui.form-panel>
@endsection
