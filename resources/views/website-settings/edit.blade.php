@extends('layouts.admin')

@section('title', 'Pagina web | '.config('app.name', 'Base Admin'))
@section('page-title', 'Pagina web')
@section('page-subtitle', 'Contenido principal visible para turistas y apps')

@section('content')
    <form method="POST" action="{{ route('website-settings.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="row g-3">
            <div class="col-lg-8">
                <x-ui.card title="Portada principal">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="hero_eyebrow">Texto superior</label>
                            <input class="form-control @error('hero_eyebrow') is-invalid @enderror" id="hero_eyebrow" name="hero_eyebrow" value="{{ old('hero_eyebrow', $settings->hero_eyebrow) }}">
                            @error('hero_eyebrow')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="logo">Logo principal</label>
                            <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept="image/*">
                            @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($settings->logo_url)
                                <label class="form-check mt-2">
                                    <input class="form-check-input" name="remove_logo" type="checkbox" value="1">
                                    <span class="form-check-label">Quitar logo actual</span>
                                </label>
                            @endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="hero_title">Titulo</label>
                            <input class="form-control @error('hero_title') is-invalid @enderror" id="hero_title" name="hero_title" value="{{ old('hero_title', $settings->hero_title) }}">
                            @error('hero_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="hero_subtitle">Descripcion</label>
                            <textarea class="form-control @error('hero_subtitle') is-invalid @enderror" id="hero_subtitle" name="hero_subtitle" rows="3">{{ old('hero_subtitle', $settings->hero_subtitle) }}</textarea>
                            @error('hero_subtitle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="hero_image">Foto de portada</label>
                            <input class="form-control @error('hero_image') is-invalid @enderror" id="hero_image" name="hero_image" type="file" accept="image/*">
                            @error('hero_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @if ($settings->hero_image_url)
                                <label class="form-check mt-2">
                                    <input class="form-check-input" name="remove_hero_image" type="checkbox" value="1">
                                    <span class="form-check-label">Quitar foto de portada actual</span>
                                </label>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card class="mt-3" title="Oferta emergente">
                    <label class="form-check form-switch mb-3">
                        <input class="form-check-input" name="popup_enabled" type="checkbox" value="1" @checked(old('popup_enabled', $settings->popup_enabled))>
                        <span class="form-check-label">Mostrar popup de oferta</span>
                    </label>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="popup_title">Titulo</label>
                            <input class="form-control" id="popup_title" name="popup_title" value="{{ old('popup_title', $settings->popup_title) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="popup_cta_label">Texto del boton</label>
                            <input class="form-control" id="popup_cta_label" name="popup_cta_label" value="{{ old('popup_cta_label', $settings->popup_cta_label) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="popup_body">Mensaje</label>
                            <textarea class="form-control" id="popup_body" name="popup_body" rows="3">{{ old('popup_body', $settings->popup_body) }}</textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="popup_cta_url">URL del boton</label>
                            <input class="form-control" id="popup_cta_url" name="popup_cta_url" value="{{ old('popup_cta_url', $settings->popup_cta_url) }}" placeholder="https://... o /tours">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="popup_image">Imagen del popup</label>
                            <input class="form-control" id="popup_image" name="popup_image" type="file" accept="image/*">
                            @if ($settings->popup_image_url)
                                <label class="form-check mt-2">
                                    <input class="form-check-input" name="remove_popup_image" type="checkbox" value="1">
                                    <span class="form-check-label">Quitar imagen actual</span>
                                </label>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card class="mt-3" title="Tours destacados y empresas">
                    <label class="form-label" for="featured_tour_ids">Carrusel de tours destacados</label>
                    <select class="form-select" id="featured_tour_ids" name="featured_tour_ids[]" multiple data-tom-select data-placeholder="Seleccionar tours destacados">
                        @foreach ($tours as $tour)
                            <option value="{{ $tour->id }}" @selected(in_array($tour->id, old('featured_tour_ids', $settings->featured_tour_ids ?? [])))>
                                {{ $tour->display_title }} @if($tour->city) - {{ $tour->city }} @endif
                            </option>
                        @endforeach
                    </select>

                    <label class="form-check form-switch mt-3">
                        <input class="form-check-input" name="show_companies" type="checkbox" value="1" @checked(old('show_companies', $settings->show_companies))>
                        <span class="form-check-label">Mostrar empresas con tours publicados</span>
                    </label>
                </x-ui.card>
            </div>

            <div class="col-lg-4">
                <x-ui.card title="Vista actual">
                    @if ($settings->logo_url)
                        <div class="mb-3">
                            <div class="text-muted small mb-1">Logo</div>
                            <img class="img-fluid rounded border" src="{{ $settings->logo_url }}" alt="Logo actual">
                        </div>
                    @endif
                    @if ($settings->hero_image_url)
                        <div class="mb-3">
                            <div class="text-muted small mb-1">Portada</div>
                            <img class="img-fluid rounded border" src="{{ $settings->hero_image_url }}" alt="Portada actual">
                        </div>
                    @endif
                    @if ($settings->popup_image_url)
                        <div>
                            <div class="text-muted small mb-1">Popup</div>
                            <img class="img-fluid rounded border" src="{{ $settings->popup_image_url }}" alt="Popup actual">
                        </div>
                    @endif
                    @unless($settings->logo_url || $settings->hero_image_url || $settings->popup_image_url)
                        <p class="text-muted mb-0">Aun no hay imagenes cargadas.</p>
                    @endunless
                </x-ui.card>

                <button class="btn btn-primary w-100 mt-3" type="submit">Guardar cambios</button>
            </div>
        </div>
    </form>
@endsection
