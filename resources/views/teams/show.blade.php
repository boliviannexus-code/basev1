@extends('layouts.admin')

@section('title', 'Equipo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Equipo')

@section('content')
    <x-ui.form-panel title="Detalle de equipo">
        @include('teams.partials.show')
    </x-ui.form-panel>
@endsection
