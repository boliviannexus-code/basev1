@extends('layouts.admin')

@section('title', 'Partido iniciado | '.config('app.name', 'Base Admin'))
@section('page-title', 'Partido iniciado')
@section('page-subtitle', ($match->matchdayDate?->matchday?->name ?? 'Jornada').' · '.($match->matchdayDate?->date?->format('d/m/Y') ?? '-'))

@section('content')
    @include('match-reports.partials.play-content')
@endsection
