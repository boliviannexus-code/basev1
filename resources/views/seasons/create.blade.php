@extends('layouts.admin')

@section('title', 'Nueva gestion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva gestion')

@section('content')
    <x-ui.form-panel title="Datos de la gestion">
        @include('seasons.partials.create-form')
    </x-ui.form-panel>
@endsection
