@extends('layouts.admin')

@section('title', 'Nuevo torneo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo torneo')

@section('content')
    <x-ui.form-panel title="Datos del torneo">
        @include('tournaments.partials.create-form')
    </x-ui.form-panel>
@endsection
