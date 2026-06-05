@extends('layouts.admin')

@section('title', 'Editar huella | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar huella')

@section('content')
    <x-ui.form-panel title="Datos de la huella">
        @include('fingerprint-templates.partials.edit-form')
    </x-ui.form-panel>
@endsection
