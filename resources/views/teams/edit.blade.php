@extends('layouts.admin')

@section('title', 'Editar equipo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar equipo')

@section('content')
    <x-ui.form-panel title="Datos del equipo">
        @include('teams.partials.edit-form')
    </x-ui.form-panel>
@endsection
