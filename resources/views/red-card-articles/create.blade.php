@extends('layouts.admin')

@section('title', 'Nuevo articulo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Nuevo articulo')
@section('page-subtitle', 'Reglamento para sanciones de tarjetas rojas')

@section('content')
    <form class="card" method="POST" action="{{ route('red-card-articles.store') }}">
        @csrf
        <div class="card-body">
            @include('red-card-articles.partials.fields')
        </div>
        <div class="card-footer text-end">
            <a class="btn btn-outline-secondary" href="{{ route('red-card-articles.index') }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar</button>
        </div>
    </form>
@endsection
