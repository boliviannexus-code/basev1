@extends('layouts.admin')

@section('title', 'Tour | '.config('app.name', 'Base Admin'))
@section('page-title', $tour->name)

@section('content')
    <x-ui.form-panel title="Detalle del tour">
        @include('tours.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('tours.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
