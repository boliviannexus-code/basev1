@extends('layouts.admin')

@section('title', 'Pagina web | '.config('app.name', 'Base Admin'))
@section('page-title', 'Pagina web')
@section('page-subtitle', 'Contenido publico del subdominio de la liga')

@section('content')
    <form class="card" method="POST" action="{{ route('website-page.update') }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="public-title">Titulo principal</label>
                    <input class="form-control @error('public_page_title') is-invalid @enderror" id="public-title" name="public_page_title" value="{{ old('public_page_title', $company->public_page_title) }}" maxlength="160">
                    @error('public_page_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">URL publica</label>
                    <div class="form-control-plaintext">
                        {{ $company->subdomain ? 'https://'.$company->subdomain.'.'.config('tenancy.base_domain') : 'Configura el subdominio de la liga' }}
                    </div>
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="public-summary">Resumen</label>
                    <textarea class="form-control @error('public_page_summary') is-invalid @enderror" id="public-summary" name="public_page_summary" rows="3" maxlength="500">{{ old('public_page_summary', $company->public_page_summary) }}</textarea>
                    @error('public_page_summary')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="public-body">Contenido institucional</label>
                    <textarea class="form-control @error('public_page_body') is-invalid @enderror" id="public-body" name="public_page_body" rows="6" maxlength="3000">{{ old('public_page_body', $company->public_page_body) }}</textarea>
                    @error('public_page_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="public-contact">Texto de contacto</label>
                    <textarea class="form-control @error('public_contact_text') is-invalid @enderror" id="public-contact" name="public_contact_text" rows="3" maxlength="1000">{{ old('public_contact_text', $company->public_contact_text) }}</textarea>
                    @error('public_contact_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="public-whatsapp">WhatsApp</label>
                    <input class="form-control @error('public_whatsapp') is-invalid @enderror" id="public-whatsapp" name="public_whatsapp" value="{{ old('public_whatsapp', $company->public_whatsapp) }}" maxlength="40" placeholder="59170000000">
                    @error('public_whatsapp')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="public-facebook">Facebook</label>
                    <input class="form-control @error('public_facebook_url') is-invalid @enderror" id="public-facebook" name="public_facebook_url" value="{{ old('public_facebook_url', $company->public_facebook_url) }}" placeholder="facebook.com/...">
                    @error('public_facebook_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="public-instagram">Instagram</label>
                    <input class="form-control @error('public_instagram_url') is-invalid @enderror" id="public-instagram" name="public_instagram_url" value="{{ old('public_instagram_url', $company->public_instagram_url) }}" placeholder="instagram.com/...">
                    @error('public_instagram_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="public-tiktok">TikTok</label>
                    <input class="form-control @error('public_tiktok_url') is-invalid @enderror" id="public-tiktok" name="public_tiktok_url" value="{{ old('public_tiktok_url', $company->public_tiktok_url) }}" placeholder="tiktok.com/@...">
                    @error('public_tiktok_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="public-youtube">YouTube</label>
                    <input class="form-control @error('public_youtube_url') is-invalid @enderror" id="public-youtube" name="public_youtube_url" value="{{ old('public_youtube_url', $company->public_youtube_url) }}" placeholder="youtube.com/...">
                    @error('public_youtube_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-12">
                    <label class="form-label" for="public-banner">Imagen banner</label>
                    <input class="form-control @error('public_banner') is-invalid @enderror" id="public-banner" name="public_banner" type="file" accept="image/jpeg,image/png,image/webp">
                    @error('public_banner')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="mt-2 rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="min-height: 160px;">
                        <img data-image-preview="public-banner" src="{{ $company->publicImageUrl($company->public_banner_path) ?: '' }}" alt="Banner" style="max-height: 180px; max-width: 100%; object-fit: contain; {{ $company->public_banner_path ? '' : 'display:none;' }}">
                        <span class="text-body-secondary small" data-image-placeholder="public-banner" @if($company->public_banner_path) style="display:none;" @endif>Selecciona una imagen para previsualizar el banner</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="public-image-one">Imagen secundaria 1</label>
                    <input class="form-control @error('public_image_one') is-invalid @enderror" id="public-image-one" name="public_image_one" type="file" accept="image/jpeg,image/png,image/webp">
                    @error('public_image_one')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="mt-2 rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="min-height: 140px;">
                        <img data-image-preview="public-image-one" src="{{ $company->publicImageUrl($company->public_image_one_path) ?: '' }}" alt="Imagen secundaria 1" style="max-height: 150px; max-width: 100%; object-fit: contain; {{ $company->public_image_one_path ? '' : 'display:none;' }}">
                        <span class="text-body-secondary small" data-image-placeholder="public-image-one" @if($company->public_image_one_path) style="display:none;" @endif>Selecciona una imagen</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="public-image-two">Imagen secundaria 2</label>
                    <input class="form-control @error('public_image_two') is-invalid @enderror" id="public-image-two" name="public_image_two" type="file" accept="image/jpeg,image/png,image/webp">
                    @error('public_image_two')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="mt-2 rounded border bg-light d-flex align-items-center justify-content-center overflow-hidden" style="min-height: 140px;">
                        <img data-image-preview="public-image-two" src="{{ $company->publicImageUrl($company->public_image_two_path) ?: '' }}" alt="Imagen secundaria 2" style="max-height: 150px; max-width: 100%; object-fit: contain; {{ $company->public_image_two_path ? '' : 'display:none;' }}">
                        <span class="text-body-secondary small" data-image-placeholder="public-image-two" @if($company->public_image_two_path) style="display:none;" @endif>Selecciona una imagen</span>
                    </div>
                </div>
            </div>

            <input type="hidden" name="public_page_is_enabled" value="0">
            <label class="form-check form-switch mt-4">
                <input class="form-check-input" name="public_page_is_enabled" type="checkbox" value="1" @checked(old('public_page_is_enabled', $company->public_page_is_enabled))>
                <span class="form-check-label">Publicar pagina</span>
            </label>
        </div>
        <div class="card-footer d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">Guardar pagina web</button>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('input[type="file"][accept*="image"]').forEach((input) => {
            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                const preview = document.querySelector(`[data-image-preview="${input.id}"]`);
                const placeholder = document.querySelector(`[data-image-placeholder="${input.id}"]`);

                if (!file || !preview) {
                    return;
                }

                if (preview.dataset.objectUrl) {
                    URL.revokeObjectURL(preview.dataset.objectUrl);
                }

                const objectUrl = URL.createObjectURL(file);
                preview.dataset.objectUrl = objectUrl;
                preview.src = objectUrl;
                preview.style.display = 'block';

                if (placeholder) {
                    placeholder.style.display = 'none';
                }
            });
        });
    </script>
@endpush
