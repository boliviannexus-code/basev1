@extends('layouts.admin')

@section('title', 'Tipo de transporte | '.config('app.name', 'Base Admin'))
@section('page-title', $transportType->title)

@section('content')
    <x-ui.form-panel title="Detalle del tipo de transporte">
        @include('transport-types.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('transport-types.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
