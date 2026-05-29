@extends('layouts.admin')

@section('title', 'Editar gestion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar gestion')

@section('content')
    <x-ui.form-panel title="Datos de la gestion">
        @include('seasons.partials.edit-form')
    </x-ui.form-panel>
@endsection
