@extends('layouts.admin')

@section('title', 'Torneo | '.config('app.name', 'Base Admin'))
@section('page-title', $tournament->name)

@section('content')
    <x-ui.form-panel title="Detalle de torneo">
        @include('tournaments.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('tournaments.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
