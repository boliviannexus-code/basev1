@extends('layouts.admin')

@section('title', 'Jugador | '.config('app.name', 'Base Admin'))
@section('page-title', $player->full_name)

@section('content')
    <x-ui.form-panel>
        @include('players.partials.show')
    </x-ui.form-panel>
@endsection
