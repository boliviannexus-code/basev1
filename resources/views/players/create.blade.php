@extends('layouts.admin')

@section('title', 'Nuevo jugador | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo jugador')

@section('content')
    <x-ui.form-panel>
        @include('players.partials.create-form')
    </x-ui.form-panel>
@endsection
