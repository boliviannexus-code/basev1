@extends('layouts.admin')

@section('title', 'Editar liga deportiva | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar liga deportiva')
@section('content')
    <x-ui.form-panel title="Datos de la liga deportiva">
        @include('companies.partials.edit-form')
    </x-ui.form-panel>
@endsection
