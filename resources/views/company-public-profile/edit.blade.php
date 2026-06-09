@extends('layouts.admin')

@section('title', 'Perfil público | '.config('app.name', 'Base Admin'))
@section('page-title', 'Perfil público')
@section('page-subtitle', 'Información visible en la página pública de la empresa')

@section('content')
    @php
        $adminStatusLabel = $company->is_online_enabled_by_admin
            ? 'Habilitada por administración'
            : ($company->is_public_enabled ? 'Pendiente de habilitación por administración' : 'Deshabilitada por administración');
        $adminStatusTone = $company->is_online_enabled_by_admin ? 'success' : ($company->is_public_enabled ? 'warning' : 'secondary');
    @endphp

    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.form-panel title="Estado de publicación">
                <dl class="row mb-0">
                    <dt class="col-5">Administración</dt>
                    <dd class="col-7">
                        <span class="badge text-bg-{{ $adminStatusTone }}">{{ $adminStatusLabel }}</span>
                    </dd>

                    <dt class="col-5">Página pública</dt>
                    <dd class="col-7">
                        <span class="badge text-bg-{{ $company->is_public_enabled ? 'success' : 'secondary' }}">
                            {{ $company->is_public_enabled ? 'Activada por empresa' : 'Desactivada por empresa' }}
                        </span>
                    </dd>

                    <dt class="col-5">Visibilidad final</dt>
                    <dd class="col-7">
                        <span class="badge text-bg-{{ $company->isPublicPageVisible() ? 'success' : 'secondary' }}">
                            {{ $company->isPublicPageVisible() ? 'Visible' : 'No visible' }}
                        </span>
                    </dd>
                </dl>

                @unless ($company->is_online_enabled_by_admin)
                    <div class="alert alert-warning mt-3 mb-0">
                        Tu página pública todavía no está habilitada por administración.
                    </div>
                @endunless

                @if ($publicUrl)
                    <hr>
                    <label class="form-label" for="public-profile-url">Link público</label>
                    <div class="input-group">
                        <input class="form-control" id="public-profile-url" value="{{ $publicUrl }}" readonly>
                        <button class="btn btn-outline-secondary" type="button" data-copy-public-url data-copy-target="#public-profile-url">
                            <i class="ti ti-copy me-1"></i>Copiar
                        </button>
                    </div>
                @endif
            </x-ui.form-panel>
        </div>

        <div class="col-lg-8">
            <x-ui.form-panel title="Datos públicos">
                <form method="POST" action="{{ route('company.public-profile.update') }}" enctype="multipart/form-data" novalidate>
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="public_name">Nombre público</label>
                            <input class="form-control @error('public_name') is-invalid @enderror" id="public_name" name="public_name" value="{{ old('public_name', $company->public_name ?: $company->name) }}" required data-public-name>
                            @error('public_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="public_slug">Slug público</label>
                            <input class="form-control @error('public_slug') is-invalid @enderror" id="public_slug" name="public_slug" value="{{ old('public_slug', $company->public_slug) }}" required data-public-slug>
                            @error('public_slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="public_description">Descripción pública</label>
                            <textarea class="form-control @error('public_description') is-invalid @enderror" id="public_description" name="public_description" rows="5">{{ old('public_description', $company->public_description) }}</textarea>
                            @error('public_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="logo">Logo</label>
                            <input class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#public-logo-preview">
                            <div class="form-hint">JPG, PNG o WebP. Máximo 4 MB.</div>
                            <div class="invalid-feedback" data-photo-error>{{ $errors->first('logo') }}</div>
                            <div class="photo-upload-preview mt-2" id="public-logo-preview" data-photo-preview>
                                @if ($company->logo)
                                    <div class="photo-upload-preview-item">
                                        <img src="{{ Storage::disk('public')->url($company->logo) }}" alt="{{ $company->public_name ?: $company->name }}">
                                        <span>Logo actual</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="cover_image">Imagen de portada</label>
                            <input class="form-control @error('cover_image') is-invalid @enderror" id="cover_image" name="cover_image" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#public-cover-preview">
                            <div class="form-hint">JPG, PNG o WebP. Máximo 4 MB.</div>
                            <div class="invalid-feedback" data-photo-error>{{ $errors->first('cover_image') }}</div>
                            <div class="photo-upload-preview mt-2" id="public-cover-preview" data-photo-preview>
                                @if ($company->cover_image)
                                    <div class="photo-upload-preview-item">
                                        <img src="{{ Storage::disk('public')->url($company->cover_image) }}" alt="{{ $company->public_name ?: $company->name }}">
                                        <span>Portada actual</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="phone">Teléfono</label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone', $company->phone) }}">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="whatsapp">WhatsApp</label>
                            <input class="form-control @error('whatsapp') is-invalid @enderror" id="whatsapp" name="whatsapp" value="{{ old('whatsapp', $company->whatsapp) }}">
                            @error('whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $company->email) }}">
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="website">Sitio web</label>
                            <input class="form-control @error('website') is-invalid @enderror" id="website" name="website" type="url" value="{{ old('website', $company->website) }}">
                            @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="facebook_url">Facebook</label>
                            <input class="form-control @error('facebook_url') is-invalid @enderror" id="facebook_url" name="facebook_url" type="url" value="{{ old('facebook_url', $company->facebook_url) }}">
                            @error('facebook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="instagram_url">Instagram</label>
                            <input class="form-control @error('instagram_url') is-invalid @enderror" id="instagram_url" name="instagram_url" type="url" value="{{ old('instagram_url', $company->instagram_url) }}">
                            @error('instagram_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="tiktok_url">TikTok</label>
                            <input class="form-control @error('tiktok_url') is-invalid @enderror" id="tiktok_url" name="tiktok_url" type="url" value="{{ old('tiktok_url', $company->tiktok_url) }}">
                            @error('tiktok_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <section class="public-profile-space-locations mt-4">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
                            <div>
                                <h3 class="h5 mb-1">Ubicaciones de tus espacios</h3>
                                <p class="text-body-secondary mb-0">Estas ubicaciones se editan desde cada espacio registrado.</p>
                            </div>
                            <span class="badge text-bg-secondary">{{ $spaces->count() }} espacio{{ $spaces->count() === 1 ? '' : 's' }}</span>
                        </div>

                        @if ($spaces->isEmpty())
                            <div class="alert alert-info mb-0">
                                Todavía no tienes espacios registrados.
                            </div>
                        @else
                            <div class="list-group">
                                @foreach ($spaces as $space)
                                    @php($location = $space->location)
                                    <div class="list-group-item">
                                        <div class="fw-semibold">{{ $space->title ?: $space->name }}</div>
                                        @if ($location)
                                            <div class="text-body-secondary small">
                                                {{ collect([$location->city, $location->country])->filter()->implode(', ') ?: 'Sin ciudad/pais' }}
                                            </div>
                                            <div class="small mt-1">{{ $location->address_text ?: $location->address }}</div>
                                            @if ($location->reference_text ?: $location->reference)
                                                <div class="small text-body-secondary">{{ $location->reference_text ?: $location->reference }}</div>
                                            @endif
                                            @if ($location->latitude && $location->longitude)
                                                <div class="small text-body-secondary mt-1">{{ $location->latitude }}, {{ $location->longitude }}</div>
                                            @endif
                                        @else
                                            <div class="text-body-secondary small">Este espacio todavía no tiene ubicación registrada.</div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>

                    <input type="hidden" name="is_public_enabled" value="0">
                    <label class="form-check form-switch mt-4">
                        <input class="form-check-input @error('is_public_enabled') is-invalid @enderror" name="is_public_enabled" type="checkbox" value="1" @checked(old('is_public_enabled', $company->is_public_enabled))>
                        <span class="form-check-label">Activar página pública</span>
                    </label>
                    @error('is_public_enabled')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">Guardar perfil público</button>
                    </div>
                </form>
            </x-ui.form-panel>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const name = document.querySelector('[data-public-name]');
            const slug = document.querySelector('[data-public-slug]');
            const copyButton = document.querySelector('[data-copy-public-url]');

            const slugify = (value) => value
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');

            name?.addEventListener('input', () => {
                if (!slug || slug.value.trim() !== '') {
                    return;
                }

                slug.value = slugify(name.value);
            });

            copyButton?.addEventListener('click', async () => {
                const target = document.querySelector(copyButton.dataset.copyTarget);

                if (!target) {
                    return;
                }

                await navigator.clipboard.writeText(target.value);
                window.Swal?.fire({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 1800,
                    icon: 'success',
                    title: 'Link copiado',
                });
            });
        });
    </script>
@endpush
