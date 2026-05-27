@extends('layouts.admin')

@section('title', 'Inscripcion | '.config('app.name', 'Base Admin'))
@section('page-title', 'Inscripcion')

@section('content')
    <x-ui.form-panel title="Detalle de inscripcion">
        @include('tournament-registrations.partials.show')
    </x-ui.form-panel>
@endsection
