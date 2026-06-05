@extends('layouts.admin')

@section('title', 'Nueva liga deportiva | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva liga deportiva')
@section('content')
    <x-ui.form-panel title="Datos de la liga deportiva">
        @include('companies.partials.create-form')
    </x-ui.form-panel>
@endsection
