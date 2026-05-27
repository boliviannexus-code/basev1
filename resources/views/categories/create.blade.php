@extends('layouts.admin')

@section('title', 'Nueva categoria | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva categoria')

@section('content')
    <x-ui.form-panel title="Datos de la categoria">
        @include('categories.partials.create-form')
    </x-ui.form-panel>
@endsection
