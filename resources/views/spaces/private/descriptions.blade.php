@extends('layouts.admin')

@section('title', 'Descripciones | '.config('app.name', 'Base Admin'))
@section('page-title', $space->title ?: 'Alojamiento privado')
@section('page-subtitle', 'Descripciones')

@section('content')
    <div data-refresh-container>
    @include('spaces.private.partials.stepper')

    <x-ui.card title="Descripciones">
        <div class="card-body">
            <form method="POST" action="{{ route('spaces.private.descriptions.store', $space) }}" data-ajax-form novalidate>
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label" for="short-description">Descripcion corta</label>
                    <textarea class="form-control @error('short_description') is-invalid @enderror" id="short-description" name="short_description" rows="4" maxlength="300" data-character-counter="#short-description-counter" required>{{ old('short_description', $space->short_description) }}</textarea>
                    <div class="form-hint d-flex justify-content-between gap-2">
                        <span>Maximo 300 caracteres.</span>
                        <span id="short-description-counter">0 caracteres</span>
                    </div>
                    <div class="invalid-feedback">{{ $errors->first('short_description') }}</div>
                </div>
                <div>
                    <label class="form-label" for="full-description">Descripcion extendida</label>
                    <textarea class="form-control @error('full_description') is-invalid @enderror" id="full-description" name="full_description" rows="8" maxlength="2000" data-character-counter="#full-description-counter" required>{{ old('full_description', $space->full_description) }}</textarea>
                    <div class="form-hint d-flex justify-content-between gap-2">
                        <span>Maximo 2000 caracteres.</span>
                        <span id="full-description-counter">0 caracteres</span>
                    </div>
                    <div class="invalid-feedback">{{ $errors->first('full_description') }}</div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a class="btn btn-outline-secondary" href="{{ route('spaces.private.details.edit', $space) }}">Volver</a>
                    <button class="btn btn-primary" type="submit">Guardar y continuar</button>
                </div>
            </form>
        </div>
    </x-ui.card>
    @include('spaces.partials.character-counter-script')
    </div>
@endsection
