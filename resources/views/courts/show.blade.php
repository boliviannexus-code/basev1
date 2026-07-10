@extends('layouts.admin')

@section('title', 'Detalle de cancha | '.config('app.name', 'Base Admin'))
@section('page-title', 'Detalle de cancha')
@section('page-subtitle', $court->name)

@section('content')
    <x-ui.table-card title="Informacion de la cancha">
        @include('courts.partials.show')
    </x-ui.table-card>
@endsection
