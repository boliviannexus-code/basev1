@extends('layouts.admin')

@section('title', 'Tipo de guia | '.config('app.name', 'Base Admin'))
@section('page-title', $guideType->title)

@section('content')
    <x-ui.form-panel title="Detalle del tipo de guia">
        @include('guide-types.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('guide-types.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
