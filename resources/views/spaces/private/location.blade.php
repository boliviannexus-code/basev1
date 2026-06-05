@extends('layouts.admin')

@section('title', 'Ubicacion | '.config('app.name', 'Base Admin'))
@section('page-title', $space->title ?: 'Alojamiento privado')
@section('page-subtitle', 'Ubicacion')

@section('content')
    <div data-refresh-container>
    @include('spaces.private.partials.stepper')

    <x-ui.card title="Ubicacion del espacio">
        <div class="card-body">
            <form method="POST" action="{{ route('spaces.private.location.store', $space) }}" data-ajax-form novalidate>
                @csrf
                @method('PUT')
                @include('spaces.partials.location-map-form')
                <div class="d-flex justify-content-between mt-4">
                    <a class="btn btn-outline-secondary" href="{{ route('spaces.private.services.edit', $space) }}">Volver</a>
                    <button class="btn btn-primary" type="submit">Guardar y continuar</button>
                </div>
            </form>
        </div>
    </x-ui.card>
    </div>
@endsection
