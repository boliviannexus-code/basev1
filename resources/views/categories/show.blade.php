@extends('layouts.admin')

@section('title', 'Categoria | '.config('app.name', 'Base Admin'))
@section('page-title', $category->name)

@section('content')
    <x-ui.form-panel title="Detalle de categoria">
        @include('categories.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('categories.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
