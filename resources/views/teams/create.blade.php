@extends('layouts.admin')

@section('title', 'Nuevo equipo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo equipo')

@section('content')
    <x-ui.form-panel title="Datos del equipo">
        @include('teams.partials.create-form')
    </x-ui.form-panel>
@endsection
