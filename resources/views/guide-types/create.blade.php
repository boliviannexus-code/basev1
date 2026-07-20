@extends('layouts.admin')

@section('title', 'Nuevo tipo de guia | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo tipo de guia')

@section('content')
    <x-ui.form-panel title="Datos del tipo de guia">
        @include('guide-types.partials.create-form')
    </x-ui.form-panel>
@endsection
