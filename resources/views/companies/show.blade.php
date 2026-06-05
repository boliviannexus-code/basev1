@extends('layouts.admin')

@section('title', 'Liga deportiva | '.config('app.name', 'Base Admin'))
@section('page-title', $company->name)
@section('content')
    <x-ui.form-panel title="Detalle de liga deportiva">
        @include('companies.partials.show')
        <x-slot:footer><a class="btn btn-outline-secondary" href="{{ route('companies.index') }}">Volver</a></x-slot:footer>
    </x-ui.form-panel>
@endsection
