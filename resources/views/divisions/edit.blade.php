@extends('layouts.admin')

@section('title', 'Editar division | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar division')

@section('content')
    <x-ui.form-panel title="Datos de la division">
        @include('divisions.partials.edit-form')
    </x-ui.form-panel>
@endsection
