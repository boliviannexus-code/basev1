@extends('layouts.admin')

@section('title', 'Nuevo tipo de transporte | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo tipo de transporte')

@section('content')
    <x-ui.form-panel title="Datos del tipo de transporte">
        @include('transport-types.partials.create-form')
    </x-ui.form-panel>
@endsection
