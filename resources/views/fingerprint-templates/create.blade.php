@extends('layouts.admin')

@section('title', 'Nueva huella | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nueva huella')

@section('content')
    <x-ui.form-panel title="Datos de la huella">
        @include('fingerprint-templates.partials.create-form')
    </x-ui.form-panel>
@endsection
