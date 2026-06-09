@csrf
@php
    $selectedSpaces = collect(old('space_ids', $package->exists ? $package->spaces->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $oldServices = collect(old('services', []));
    $packageServices = $package->exists ? $package->services->keyBy('id') : collect();
    $badgesValue = old('badges', collect($package->badges ?? [])->implode(', '));
    $serviceIcon = function ($service): string {
        if (filled($service->icon)) {
            $icon = trim((string) $service->icon);

            if (str_starts_with($icon, 'ti ')) {
                return $icon;
            }

            return str_starts_with($icon, 'ti-') ? 'ti '.$icon : 'ti ti-'.$icon;
        }

        return match ($service->type) {
            'alojamiento' => 'ti ti-home',
            'transporte' => 'ti ti-car',
            'alimentacion' => 'ti ti-tools-kitchen-2',
            'tour' => 'ti ti-map',
            'bienestar' => 'ti ti-spa',
            'decoracion' => 'ti ti-sparkles',
            'aventura' => 'ti ti-mountain',
            'equipamiento' => 'ti ti-backpack',
            default => 'ti ti-circle-check',
        };
    };
@endphp

<section>
    <h3 class="h5">Oferta comercial</h3>
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="name">Nombre del paquete</label>
                    <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $package->name) }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="price_display_text">Texto comercial de precio</label>
                    <input class="form-control @error('price_display_text') is-invalid @enderror" id="price_display_text" name="price_display_text" value="{{ old('price_display_text', $package->price_display_text) }}" placeholder="990 Bs la pareja">
                    <div class="form-hint">Ej.: 600 Bs por paquete / incluye 2 personas.</div>
                    @error('price_display_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="short_description">Descripcion corta</label>
                    <input class="form-control @error('short_description') is-invalid @enderror" id="short_description" name="short_description" value="{{ old('short_description', $package->short_description) }}" required>
                    @error('short_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="badges">Etiquetas comerciales</label>
                    <input class="form-control @error('badges') is-invalid @enderror" id="badges" name="badges" value="{{ $badgesValue }}" placeholder="Ideal para pareja, Experiencia romantica, Todo incluido">
                    <div class="form-hint">Separalas por coma.</div>
                    @error('badges')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="video_url">Video del paquete</label>
                    <input class="form-control @error('video_url') is-invalid @enderror" id="video_url" name="video_url" type="url" value="{{ old('video_url', $package->video_url) }}" placeholder="https://www.youtube.com/watch?v=... o https://www.tiktok.com/@.../video/...">
                    <div class="form-hint">Acepta enlaces de YouTube o TikTok.</div>
                    @error('video_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <label class="form-label" for="main_image">Imagen principal</label>
            <input class="form-control @error('main_image') is-invalid @enderror" id="main_image" name="main_image" type="file" accept="image/jpeg,image/png,image/webp" data-photo-input data-photo-max-size="4096" data-photo-max-files="1" data-photo-preview="#package-main-image-preview" data-photo-clear="#package-main-image-clear">
            <div class="invalid-feedback" data-photo-error>{{ $errors->first('main_image') }}</div>
            <div class="photo-upload-preview package-main-image-preview mt-2" id="package-main-image-preview" data-photo-preview>
                @if ($package->main_image)
                    <div class="photo-upload-preview-item">
                        <img src="{{ Storage::disk('public')->url($package->main_image) }}" alt="{{ $package->name ?: 'Imagen del paquete' }}">
                        <span>Imagen actual</span>
                    </div>
                @endif
            </div>
            <button class="btn btn-outline-secondary btn-sm mt-2 d-none" id="package-main-image-clear" type="button" data-photo-clear-button>
                <i class="ti ti-x me-1"></i>Quitar imagen seleccionada
            </button>
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="commercial_description">Descripcion comercial</label>
            <textarea class="form-control @error('commercial_description') is-invalid @enderror" id="commercial_description" name="commercial_description" rows="4">{{ old('commercial_description', $package->commercial_description) }}</textarea>
            @error('commercial_description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-lg-6">
            <label class="form-label" for="conditions">Condiciones</label>
            <textarea class="form-control @error('conditions') is-invalid @enderror" id="conditions" name="conditions" rows="4">{{ old('conditions', $package->conditions) }}</textarea>
            @error('conditions')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</section>

<hr>

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label" for="price">Precio cerrado</label>
        <input class="form-control @error('price') is-invalid @enderror" id="price" name="price" type="number" min="0" step="0.01" value="{{ old('price', $package->price) }}" required>
        @error('price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label" for="currency">Moneda</label>
        <input class="form-control @error('currency') is-invalid @enderror" id="currency" name="currency" maxlength="3" value="{{ old('currency', $package->currency ?: 'BOB') }}">
        @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label" for="included_people">Personas incluidas</label>
        <input class="form-control @error('included_people') is-invalid @enderror" id="included_people" name="included_people" type="number" min="1" value="{{ old('included_people', $package->included_people ?: 1) }}" required>
        @error('included_people')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-2">
        <label class="form-label" for="max_people">Max. personas</label>
        <input class="form-control @error('max_people') is-invalid @enderror" id="max_people" name="max_people" type="number" min="1" value="{{ old('max_people', $package->max_people) }}">
        @error('max_people')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="extra_person_price">Precio persona extra</label>
        <input class="form-control @error('extra_person_price') is-invalid @enderror" id="extra_person_price" name="extra_person_price" type="number" min="0" step="0.01" value="{{ old('extra_person_price', $package->extra_person_price) }}">
        @error('extra_person_price')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="nights_included">Noches incluidas</label>
        <input class="form-control @error('nights_included') is-invalid @enderror" id="nights_included" name="nights_included" type="number" min="1" value="{{ old('nights_included', $package->nights_included ?: 1) }}" required>
        @error('nights_included')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-3">
        <label class="form-label" for="sort_order">Orden</label>
        <input class="form-control @error('sort_order') is-invalid @enderror" id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $package->sort_order ?? 0) }}">
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6 d-flex align-items-end gap-4">
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="requires_full_private_space" value="0">
            <input class="form-check-input" name="requires_full_private_space" type="checkbox" value="1" @checked(old('requires_full_private_space', $package->requires_full_private_space ?? true))>
            <span class="form-check-label">Requiere espacio privado completo</span>
        </label>
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" name="is_active" type="checkbox" value="1" @checked(old('is_active', $package->is_active ?? true))>
            <span class="form-check-label">Activo</span>
        </label>
        <label class="form-check form-switch mb-2">
            <input type="hidden" name="is_featured" value="0">
            <input class="form-check-input" name="is_featured" type="checkbox" value="1" @checked(old('is_featured', $package->is_featured ?? false))>
            <span class="form-check-label">Destacado</span>
        </label>
    </div>
</div>

<hr>

<section>
    <h3 class="h5">Espacios privados</h3>
    @error('space_ids')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="row g-2">
        @forelse ($spaces as $space)
            <div class="col-md-6">
                <label class="form-check border rounded p-3 h-100">
                    <input class="form-check-input" name="space_ids[]" type="checkbox" value="{{ $space->id }}" @checked($selectedSpaces->contains((int) $space->id))>
                    <span class="form-check-label">
                        <strong>{{ $space->title ?: $space->name }}</strong>
                        <span class="d-block text-body-secondary small">{{ $space->max_capacity ?: 0 }} persona{{ (int) $space->max_capacity === 1 ? '' : 's' }}</span>
                    </span>
                </label>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning mb-0">No hay espacios privados activos para asociar paquetes.</div>
            </div>
        @endforelse
    </div>
</section>

<hr>

<section>
    <h3 class="h5">Servicios del paquete</h3>
    @error('services')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="package-service-cart" data-package-service-cart>
        <div class="package-service-catalog">
            <div class="package-service-panel-heading">
                <div>
                    <strong>Catalogo disponible</strong>
                    <span>Agrega servicios al paquete</span>
                </div>
            </div>
            <div class="package-service-catalog-list">
                @forelse ($services as $service)
                    @php
                        $oldService = $oldServices->firstWhere('service_id', $service->id);
                        $pivot = $packageServices->get($service->id)?->pivot;
                        $inclusion = $oldService['inclusion_type'] ?? $pivot?->inclusion_type;
                        $isSelected = filled($inclusion);
                    @endphp
                    <article class="package-service-card {{ $isSelected ? 'is-selected' : '' }}" data-package-service-card="{{ $service->id }}">
                        <span class="package-service-card-icon"><i class="{{ $serviceIcon($service) }}"></i></span>
                        <div>
                            <strong>{{ $service->name }}</strong>
                            <span>{{ $service->type ? str($service->type)->headline() : 'Sin tipo' }}</span>
                        </div>
                        <button class="btn btn-outline-primary btn-sm" type="button"
                            data-package-service-add
                            data-service-id="{{ $service->id }}"
                            data-service-name="{{ $service->name }}"
                            data-service-type="{{ $service->type ? str($service->type)->headline() : 'Sin tipo' }}"
                            data-service-icon="{{ $serviceIcon($service) }}"
                            @disabled($isSelected)>
                            <i class="ti ti-plus"></i>
                            Agregar
                        </button>
                    </article>
                @empty
                    <div class="alert alert-warning mb-0">Crea servicios de paquetes antes de asociarlos.</div>
                @endforelse
            </div>
        </div>

        <div class="package-service-selected">
            <div class="package-service-panel-heading">
                <div>
                    <strong>Servicios agregados</strong>
                    <span><span data-package-service-count>0</span> en el paquete</span>
                </div>
            </div>
            <div class="package-service-selected-list" data-package-service-selected-list>
                @foreach ($services as $service)
                    @php
                        $oldService = $oldServices->firstWhere('service_id', $service->id);
                        $pivot = $packageServices->get($service->id)?->pivot;
                        $inclusion = $oldService['inclusion_type'] ?? $pivot?->inclusion_type;
                    @endphp
                    @if (filled($inclusion))
                        <article class="package-service-selected-item" draggable="true" data-package-service-selected="{{ $service->id }}">
                            <input type="hidden" name="services[{{ $loop->index }}][service_id]" value="{{ $service->id }}" data-package-service-input="service_id">
                            <input type="hidden" name="services[{{ $loop->index }}][sort_order]" value="{{ $oldService['sort_order'] ?? $pivot?->sort_order ?? $loop->index }}" data-package-service-input="sort_order">
                            <div class="package-service-selected-top">
                                <span class="package-service-drag-handle" title="Arrastrar para ordenar"><i class="ti ti-grip-vertical"></i></span>
                                <span class="package-service-card-icon"><i class="{{ $serviceIcon($service) }}"></i></span>
                                <div>
                                    <strong>{{ $service->name }}</strong>
                                    <span>{{ $service->type ? str($service->type)->headline() : 'Sin tipo' }}</span>
                                </div>
                                <button class="btn btn-outline-danger btn-sm" type="button" data-package-service-remove>
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                            <div class="package-service-selected-fields">
                                <div>
                                    <label class="form-label">Inclusion</label>
                                    <select class="form-select form-select-sm" name="services[{{ $loop->index }}][inclusion_type]" data-package-service-input="inclusion_type">
                                        <option value="included" @selected($inclusion === 'included')>Incluido</option>
                                        <option value="optional_paid" @selected($inclusion === 'optional_paid')>Opcional con costo</option>
                                        <option value="not_included" @selected($inclusion === 'not_included')>No incluido</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Precio adicional</label>
                                    <input class="form-control form-control-sm" name="services[{{ $loop->index }}][additional_price]" type="number" min="0" step="0.01" value="{{ $inclusion === 'optional_paid' ? ($oldService['additional_price'] ?? $pivot?->additional_price) : '' }}" data-package-service-input="additional_price" @disabled($inclusion !== 'optional_paid')>
                                </div>
                            </div>
                        </article>
                    @endif
                @endforeach
            </div>
            <div class="package-service-empty" data-package-service-empty>
                <i class="ti ti-shopping-cart-plus"></i>
                <strong>Aun no agregaste servicios</strong>
                <span>Usa el catalogo para armar el paquete.</span>
            </div>
        </div>

        <template data-package-service-template>
            <article class="package-service-selected-item" draggable="true" data-package-service-selected="__SERVICE_ID__">
                <input type="hidden" name="services[__INDEX__][service_id]" value="__SERVICE_ID__" data-package-service-input="service_id">
                <input type="hidden" name="services[__INDEX__][sort_order]" value="__INDEX__" data-package-service-input="sort_order">
                <div class="package-service-selected-top">
                    <span class="package-service-drag-handle" title="Arrastrar para ordenar"><i class="ti ti-grip-vertical"></i></span>
                    <span class="package-service-card-icon"><i class="__ICON__"></i></span>
                    <div>
                        <strong>__NAME__</strong>
                        <span>__TYPE__</span>
                    </div>
                    <button class="btn btn-outline-danger btn-sm" type="button" data-package-service-remove>
                        <i class="ti ti-trash"></i>
                    </button>
                </div>
                <div class="package-service-selected-fields">
                    <div>
                        <label class="form-label">Inclusion</label>
                        <select class="form-select form-select-sm" name="services[__INDEX__][inclusion_type]" data-package-service-input="inclusion_type">
                            <option value="included">Incluido</option>
                            <option value="optional_paid">Opcional con costo</option>
                            <option value="not_included">No incluido</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Precio adicional</label>
                        <input class="form-control form-control-sm" name="services[__INDEX__][additional_price]" type="number" min="0" step="0.01" data-package-service-input="additional_price" disabled>
                    </div>
                </div>
            </article>
        </template>
    </div>
</section>

<div class="mt-4 d-flex justify-content-end gap-2">
    <a class="btn btn-outline-secondary" href="{{ route('accommodation-packages.index') }}">Cancelar</a>
    <button class="btn btn-primary" type="submit">Guardar paquete</button>
</div>
