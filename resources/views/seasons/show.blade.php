@extends('layouts.admin')

@section('title', 'Gestion | '.config('app.name', 'Base Admin'))
@section('page-title', $season->name)

@section('content')
    <x-ui.form-panel title="Detalle de gestion">
        @include('seasons.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('seasons.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
