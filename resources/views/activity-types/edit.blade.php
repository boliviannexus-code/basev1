@extends('layouts.admin')

@section('title', 'Editar tipo de actividad | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar tipo de actividad')

@section('content')
    <x-ui.form-panel title="Datos del tipo de actividad">
        @include('activity-types.partials.edit-form')
    </x-ui.form-panel>
@endsection
