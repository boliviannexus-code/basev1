@extends('layouts.admin')

@section('title', 'Editar tipo de transporte | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar tipo de transporte')

@section('content')
    <x-ui.form-panel title="Datos del tipo de transporte">
        @include('transport-types.partials.edit-form')
    </x-ui.form-panel>
@endsection
