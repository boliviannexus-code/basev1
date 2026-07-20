@extends('layouts.admin')

@section('title', 'Editar articulo | '.config('app.name', 'Base Admin'))
@section('page-title', 'Editar articulo')
@section('page-subtitle', 'Reglamento para sanciones de tarjetas rojas')

@section('content')
    <form class="card" method="POST" action="{{ route('red-card-articles.update', $article) }}">
        @csrf
        @method('PUT')
        <div class="card-body">
            @include('red-card-articles.partials.fields')
        </div>
        <div class="card-footer text-end">
            <a class="btn btn-outline-secondary" href="{{ route('red-card-articles.index') }}">Cancelar</a>
            <button class="btn btn-primary" type="submit">Guardar cambios</button>
        </div>
    </form>
@endsection
