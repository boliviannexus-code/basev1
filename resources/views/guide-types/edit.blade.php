@extends('layouts.admin')

@section('title', 'Editar tipo de guia | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar tipo de guia')

@section('content')
    <x-ui.form-panel title="Datos del tipo de guia">
        @include('guide-types.partials.edit-form')
    </x-ui.form-panel>
@endsection
