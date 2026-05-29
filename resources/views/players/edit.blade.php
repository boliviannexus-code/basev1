@extends('layouts.admin')

@section('title', 'Editar jugador | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar jugador')

@section('content')
    <x-ui.form-panel>
        @include('players.partials.edit-form')
    </x-ui.form-panel>
@endsection
