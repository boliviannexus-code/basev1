@extends('layouts.admin')

@section('title', 'Nueva division | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva division')

@section('content')
    <x-ui.form-panel title="Datos de la division">
        @include('divisions.partials.create-form')
    </x-ui.form-panel>
@endsection
