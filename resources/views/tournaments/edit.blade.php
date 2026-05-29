@extends('layouts.admin')

@section('title', 'Editar torneo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar torneo')

@section('content')
    <x-ui.form-panel title="Datos del torneo">
        @include('tournaments.partials.edit-form')
    </x-ui.form-panel>
@endsection
