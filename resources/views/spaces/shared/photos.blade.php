@extends('layouts.admin')

@section('title', 'Fotografias | '.config('app.name', 'Base Admin'))
@section('page-title', $space->name ?: 'Alojamiento compartido')
@section('page-subtitle', 'Fotos generales y por habitacion')

@section('content')
    <div data-refresh-container>
    @include('spaces.shared.partials.stepper')
    @php
        $mainPhoto = $space->photos->firstWhere('type', 'main');
        $galleryPhotos = $space->photos->where('type', 'gallery');
    @endphp
    <x-ui.card title="Fotografias generales" class="mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('spaces.shared.photos.store', $space) }}" enctype="multipart/form-data" data-ajax-form data-photo-upload-form>
                @csrf
                @method('PUT')
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Foto principal</label>
                        <input class="form-control" name="main_photo" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#shared-main-photo-preview">
                        <div class="form-hint">1 foto principal del alojamiento.</div>
                        <div class="invalid-feedback" data-photo-error></div>
                        <div class="photo-upload-preview mt-2" id="shared-main-photo-preview" data-photo-preview></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Galeria complementaria</label>
                        <input class="form-control" name="gallery_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-photo-max-size="4096" data-photo-max-files="3" data-photo-preview="#shared-gallery-photos-preview">
                        <div class="form-hint">Maximo 3 fotografias de galeria. JPG, PNG o WebP. Maximo 4 MB cada una.</div>
                        <div class="invalid-feedback" data-photo-error></div>
                        <div class="photo-upload-preview mt-2" id="shared-gallery-photos-preview" data-photo-preview></div>
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input class="form-check-input" name="photos_skipped" type="checkbox" value="1" @checked(old('photos_skipped', $space->photos_skipped))>
                            <span class="form-check-label">No usar fotos generales del alojamiento</span>
                        </label>
                        <div class="form-hint">Si marcas esta opcion, la foto principal general no bloqueara la publicacion.</div>
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit">Guardar fotos generales</button>
            </form>

            @if ($mainPhoto || $galleryPhotos->isNotEmpty())
                <div class="mt-4">
                    <h3 class="card-title mb-3">Fotos generales cargadas</h3>

                    @if ($mainPhoto)
                        <div class="mb-3">
                            <div class="text-body-secondary small mb-2">Principal</div>
                            <div class="space-photo-item space-photo-item-main">
                                <img class="space-photo-preview" src="{{ Storage::disk('public')->url($mainPhoto->path) }}" alt="{{ $space->name }}">
                                <div class="photo-loaded-message">Foto cargada. Eliminala para subir una nueva principal.</div>
                                <form method="POST" action="{{ route('spaces.shared.photos.destroy', [$space, $mainPhoto]) }}" data-ajax-form data-confirm-delete="Eliminar fotografia principal?">
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
                                    <img class="space-photo-thumb" src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $photo->alt_text ?: $space->name }}">
                                    <div class="photo-loaded-message">Cargada</div>
                                    <form method="POST" action="{{ route('spaces.shared.photos.destroy', [$space, $photo]) }}" data-ajax-form data-confirm-delete="Eliminar fotografia?">
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

    @foreach ($space->rooms as $room)
        <x-ui.card :title="'Fotos: '.$room->title" class="mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('spaces.shared.room-photos.store', [$space, $room]) }}" enctype="multipart/form-data" data-ajax-form data-photo-upload-form>
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Foto principal de habitacion</label>
                            <input class="form-control" name="main_photo" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#room-main-photo-preview-{{ $room->id }}">
                            <div class="form-hint">1 foto principal de la habitacion. Maximo 4 MB.</div>
                            <div class="invalid-feedback" data-photo-error></div>
                            <div class="photo-upload-preview mt-2" id="room-main-photo-preview-{{ $room->id }}" data-photo-preview></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Galeria de habitacion</label>
                            <input class="form-control" name="gallery_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple data-photo-input data-photo-max-size="4096" data-photo-max-files="3" data-photo-preview="#room-gallery-photos-preview-{{ $room->id }}">
                            <div class="form-hint">Maximo 3 fotografias de habitacion. JPG, PNG o WebP. Maximo 4 MB cada una.</div>
                            <div class="invalid-feedback" data-photo-error></div>
                            <div class="photo-upload-preview mt-2" id="room-gallery-photos-preview-{{ $room->id }}" data-photo-preview></div>
                        </div>
                        <div class="col-12">
                            <label class="form-check">
                                <input class="form-check-input" name="photos_skipped" type="checkbox" value="1" data-auto-submit-form @checked(old('photos_skipped', $room->photos_skipped))>
                                <span class="form-check-label">No usar fotos para esta habitacion</span>
                            </label>
                            <div class="form-hint">Si marcas esta opcion, las fotos de esta habitacion no bloquearan la publicacion.</div>
                        </div>
                    </div>
                    <button class="btn btn-primary mt-3" type="submit">Guardar fotos de habitacion</button>
                </form>

                    @if ($room->photos->isNotEmpty())
                        <div class="space-photo-grid mt-3">
                            @foreach ($room->photos as $photo)
                                <div class="space-photo-item">
                                    <img class="space-photo-thumb" src="{{ Storage::disk('public')->url($photo->path) }}" alt="{{ $room->title }}">
                                    <div class="photo-loaded-message">Cargada. Eliminala para reemplazar.</div>
                                    <form method="POST" action="{{ route('spaces.shared.room-photos.destroy', [$space, $room, $photo]) }}" data-ajax-form data-confirm-delete="Eliminar fotografia de habitacion?">
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
        </x-ui.card>
    @endforeach

    <div class="d-flex justify-content-between mt-4">
        <a class="btn btn-outline-secondary" href="{{ route('spaces.shared.room-services.edit', $space) }}">Volver</a>
        <a class="btn btn-primary" href="{{ route('spaces.shared.services.edit', $space) }}">Continuar</a>
    </div>
    </div>
@endsection
