@extends('layouts.admin')

@section('title', 'Division | '.config('app.name', 'Base Admin'))
@section('page-title', $division->name)

@section('content')
    <x-ui.form-panel title="Detalle de division">
        @include('divisions.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('divisions.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
