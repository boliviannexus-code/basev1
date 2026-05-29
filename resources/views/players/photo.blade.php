@extends('layouts.admin')

@section('title', 'Fotografia del jugador | '.config('app.name', 'Base Admin'))
@section('page-title', 'Fotografia del jugador')
@section('page-subtitle', $player->full_name)

@section('content')
    <x-ui.form-panel>
        @include('players.partials.photo-form')
    </x-ui.form-panel>
@endsection
