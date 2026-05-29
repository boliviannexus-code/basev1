@extends('layouts.admin')

@section('title', 'Tipo de actividad | '.config('app.name', 'Base Admin'))
@section('page-title', $activityType->title)

@section('content')
    <x-ui.form-panel title="Detalle del tipo de actividad">
        @include('activity-types.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('activity-types.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
