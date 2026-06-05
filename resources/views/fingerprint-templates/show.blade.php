@extends('layouts.admin')

@section('title', 'Huella | '.config('app.name', 'Base Admin'))
@section('page-title', 'Huella')

@section('content')
    <x-ui.form-panel title="Detalle de huella">
        @include('fingerprint-templates.partials.show')
    </x-ui.form-panel>
@endsection
