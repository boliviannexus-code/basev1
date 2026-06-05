@extends('layouts.admin')

@section('title', 'Registro biometrico | '.config('app.name', 'Base Admin'))
@section('page-title', 'Registro biometrico')
@section('page-subtitle', $player->full_name)

@section('content')
    <x-ui.form-panel>
        @include('players.partials.biometric-registration-form')
    </x-ui.form-panel>
@endsection
