@extends('layouts.admin')

@section('title', 'Editar categoria | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar categoria')

@section('content')
    <x-ui.form-panel title="Datos de la categoria">
        @include('categories.partials.edit-form')
    </x-ui.form-panel>
@endsection
