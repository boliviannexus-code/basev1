@extends('layouts.admin')

@section('title', 'Datos compartido | '.config('app.name', 'Base Admin'))
@section('page-title', 'Alojamiento compartido')
@section('page-subtitle', 'Datos principales')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')
    <x-ui.card title="Datos principales del alojamiento">
        <div class="card-body">
            <form method="POST" action="{{ route('spaces.shared.details.store', $space) }}" data-ajax-form novalidate>
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="shared-space-type">Tipo de alojamiento compartido</label>
                        <select class="form-select @error('shared_space_type_id') is-invalid @enderror" id="shared-space-type" name="shared_space_type_id" data-tom-select required>
                            <option value="">Seleccionar</option>
                            @foreach ($sharedSpaceTypes as $type)
                                <option value="{{ $type->id }}" @selected((int) old('shared_space_type_id', $space->shared_space_type_id) === $type->id)>{{ $type->name }}</option>
                            @endforeach
                        </select>
                        <div class="invalid-feedback">{{ $errors->first('shared_space_type_id') }}</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="space-name">Nombre del alojamiento</label>
                        <input class="form-control @error('name') is-invalid @enderror" id="space-name" name="name" value="{{ old('name', $space->name) }}" required>
                        <div class="invalid-feedback">{{ $errors->first('name') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="short-description">Descripcion corta</label>
                        <textarea class="form-control @error('short_description') is-invalid @enderror" id="short-description" name="short_description" rows="4" maxlength="300" data-character-counter="#short-description-counter" required>{{ old('short_description', $space->short_description) }}</textarea>
                        <div class="form-hint d-flex justify-content-between gap-2">
                            <span>Maximo 300 caracteres.</span>
                            <span id="short-description-counter">0 caracteres</span>
                        </div>
                        <div class="invalid-feedback">{{ $errors->first('short_description') }}</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="full-description">Descripcion extendida</label>
                        <textarea class="form-control @error('full_description') is-invalid @enderror" id="full-description" name="full_description" rows="8" maxlength="2000" data-character-counter="#full-description-counter" required>{{ old('full_description', $space->full_description) }}</textarea>
                        <div class="form-hint d-flex justify-content-between gap-2">
                            <span>Maximo 2000 caracteres.</span>
                            <span id="full-description-counter">0 caracteres</span>
                        </div>
                        <div class="invalid-feedback">{{ $errors->first('full_description') }}</div>
                    </div>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <button class="btn btn-primary" type="submit">Guardar y continuar</button>
                </div>
            </form>
        </div>
    </x-ui.card>
    @include('spaces.partials.character-counter-script')
    </div>
@endsection
