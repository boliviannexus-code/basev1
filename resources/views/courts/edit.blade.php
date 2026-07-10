@extends('layouts.admin')

@section('title', 'Editar cancha | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar cancha')
@section('page-subtitle', $court->name)

@section('content')
    <x-ui.form-panel title="Datos de la cancha">
        @include('courts.partials.edit-form')
    </x-ui.form-panel>
@endsection
