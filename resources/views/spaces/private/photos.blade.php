@extends('layouts.admin')

@section('title', 'Fotografias | '.config('app.name', 'Base Admin'))
@section('page-title', $space->title ?: 'Alojamiento privado')
@section('page-subtitle', 'Fotografias')

@section('content')
    <div data-refresh-container>
    @include('spaces.private.partials.stepper')

    @php
        $mainPhoto = $space->photos->firstWhere('type', 'main');
        $galleryPhotos = $space->photos->where('type', 'gallery');
    @endphp

    <x-ui.card title="Fotografias">
        <div class="card-body">
            <form method="POST" action="{{ route('spaces.private.photos.store', $space) }}" enctype="multipart/form-data" data-ajax-form data-photo-upload-form>
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="main-photo">Foto principal</label>
                        <input class="form-control @error('main_photo') is-invalid @enderror" id="main-photo" name="main_photo" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#main-photo-preview">
                        <div class="form-hint">1 foto principal. JPG, PNG o WebP. Maximo 4 MB.</div>
                        <div class="invalid-feedback" data-photo-error>{{ $errors->first('main_photo') }}</div>
                        <div class="photo-upload-preview mt-2" id="main-photo-preview" data-photo-preview></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="gallery-photos">Fotografias complementarias</label>
                        <input class="form-control @error('gallery_photos') is-invalid @enderror @error('gallery_photos.*') is-invalid @enderror" id="gallery-photos" name="gallery_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-photo-max-size="4096" data-photo-max-files="5" data-photo-preview="#gallery-photos-preview">
                        <div class="form-hint">Maximo 5 imagenes complementarias.</div>
                        <div class="invalid-feedback" data-photo-error>{{ $errors->first('gallery_photos') ?: $errors->first('gallery_photos.*') }}</div>
                        <div class="photo-upload-preview mt-2" id="gallery-photos-preview" data-photo-preview></div>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" name="photos_skipped" type="checkbox" value="1" @checked(old('photos_skipped', $space->photos_skipped))>
                            <span class="form-check-label">No usar fotos para este alojamiento</span>
                        </label>
                        <div class="form-hint">Si marcas esta opcion, el paso de fotos no bloqueara la publicacion.</div>
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4">
                    <a class="btn btn-outline-secondary" href="{{ route('spaces.private.descriptions.edit', $space) }}">Volver</a>
                    <button class="btn btn-primary" type="submit">Guardar y continuar</button>
                </div>
            </form>

            @if ($mainPhoto || $galleryPhotos->isNotEmpty())
                <div class="mt-4">
                    <h3 class="card-title mb-3">Fotos cargadas</h3>

                    @if ($mainPhoto)
                        <div class="mb-3">
                            <div class="text-body-secondary small mb-2">Principal</div>
                            <div class="space-photo-item space-photo-item-main">
                                <img class="space-photo-preview" src="{{ Storage::disk('public')->url($mainPhoto->path) }}" alt="{{ $space->title }}">
                                <div class="photo-loaded-message">Foto cargada. Eliminala para subir una nueva principal.</div>
                                <form method="POST" action="{{ route('spaces.private.photos.destroy', [$space, $mainPhoto]) }}" data-ajax-form data-confirm-delete="Eliminar fotografia principal?">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-danger btn-icon btn-sm space-photo-delete" type="submit" aria-label="Eliminar foto">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @if ($galleryPhotos->isNotEmpty())
                        <div class="text-body-secondary small mb-2">Galeria</div>
                        <div class="space-photo-grid">
                            @foreach ($galleryPhotos as $photo)
                                <div class="space-photo-item">
                                    <img class="space-photo-thumb" src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt_text ?: $space->title }}">
                                    <div class="photo-loaded-message">Cargada</div>
                                    <form method="POST" action="{{ route('spaces.private.photos.destroy', [$space, $photo]) }}" data-ajax-form data-confirm-delete="Eliminar fotografia?">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-danger btn-icon btn-sm space-photo-delete" type="submit" aria-label="Eliminar foto">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </x-ui.card>
    </div>
@endsection
