@extends('layouts.admin')

@php
    $isNew = $tour === null;
    $currentStep = $step ?? 1;
    $savedStep = $tour?->current_step ?? 1;
    $progress = (int) round(($currentStep / count($steps)) * 100);
    $keywordsValue = old('keywords', implode(',', $tour?->keywords ?? []));
    $itineraryDays = old('itinerary_days');
    if ($itineraryDays === null) {
        $itineraryDays = $tour?->itineraryDays?->map(fn ($day) => [
            'day_number' => $day->day_number,
            'title' => $day->title,
            'summary' => $day->summary,
            'stops' => $day->stops->map(fn ($stop) => [
                'activity_type_id' => $stop->activity_type_id,
                'position' => $stop->position,
                'start_time' => $stop->start_time?->format('H:i'),
                'title' => $stop->title,
                'location_name' => $stop->location_name,
            ])->all(),
        ])->all();
    }
    if (blank($itineraryDays)) {
        $itineraryDays = [[
            'day_number' => 1,
            'title' => 'Dia 1',
            'summary' => '',
            'stops' => [[
                'activity_type_id' => $activityTypes->first()?->id,
                'position' => 1,
                'start_time' => '08:00',
                'title' => '',
                'location_name' => '',
            ]],
        ]];
    }
@endphp

@section('title', ($isNew ? 'Nuevo tour' : 'Editar tour').' | '.config('app.name', 'Base Admin'))
@section('page-title', $isNew ? 'Nuevo tour' : ($tour->display_title ?: 'Borrador de tour'))
@section('page-subtitle', 'Registro guiado con guardado parcial')

@push('styles')
    <style>
        .tour-wizard-layout { display: grid; grid-template-columns: minmax(220px, 280px) minmax(0, 1fr); gap: 1rem; align-items: start; }
        .tour-stepper { position: sticky; top: 1rem; }
        .tour-step-item { display: flex; gap: .75rem; padding: .75rem; border-radius: 8px; color: var(--tblr-body-color); text-decoration: none; border: 1px solid transparent; }
        .tour-step-item.is-active { background: rgba(var(--tblr-primary-rgb), .08); border-color: rgba(var(--tblr-primary-rgb), .28); color: var(--tblr-primary); }
        .tour-step-item.is-done .tour-step-number { background: var(--tblr-success); color: #fff; }
        .tour-step-number { width: 2rem; height: 2rem; display: inline-flex; align-items: center; justify-content: center; border-radius: 999px; background: var(--tblr-border-color); font-weight: 700; flex: 0 0 auto; }
        .tour-step-title { font-weight: 600; line-height: 1.2; }
        .tour-step-status { font-size: .75rem; color: var(--tblr-secondary); }
        .tour-wizard-card { border-radius: 8px; }
        .keyword-chip { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid var(--tblr-border-color); border-radius: 999px; padding: .35rem .65rem; background: var(--tblr-bg-surface); }
        .keyword-chip button { border: 0; background: transparent; color: var(--tblr-secondary); padding: 0; line-height: 1; }
        .tour-image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: .75rem; }
        .tour-image-card { border: 1px solid var(--tblr-border-color); border-radius: 8px; overflow: hidden; background: var(--tblr-bg-surface); }
        .tour-image-card img { width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
        .tour-rejection-alert { border: 2px solid var(--tblr-danger); background: rgba(var(--tblr-danger-rgb), .1); color: var(--tblr-danger); }
        .tour-rejection-title { color: var(--tblr-danger); font-size: 1.1rem; font-weight: 800; text-transform: uppercase; }
        .tour-rejection-alert li { font-weight: 700; }
        .itinerary-day { border: 1px solid var(--tblr-border-color); border-radius: 8px; background: var(--tblr-bg-surface); }
        .itinerary-day-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid var(--tblr-border-color); }
        .itinerary-stop { border: 1px solid var(--tblr-border-color); border-radius: 8px; padding: .75rem; background: var(--tblr-bg-surface-secondary, var(--tblr-bg-surface)); }
        @media (max-width: 991px) { .tour-wizard-layout { grid-template-columns: 1fr; } .tour-stepper { position: static; } }
    </style>
@endpush

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Hay datos pendientes por corregir.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($tour?->review_status === \App\Models\Tour::REVIEW_REJECTED && $tour->rejection_points)
        <div class="alert tour-rejection-alert">
            <div class="tour-rejection-title mb-1">Debe corregir este tour</div>
            <div class="fw-semibold mb-2">Correcciones actuales</div>
            <ul class="mb-0">
                @foreach ($tour->rejection_points as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
            @if ($tour->correction_history && count($tour->correction_history) > 1)
                <div class="fw-semibold mt-3 mb-2">Historial de correcciones solicitadas</div>
                @foreach ($tour->correction_history as $historyIndex => $entry)
                    <div class="mb-2">
                        <div class="small fw-bold">Revision {{ $historyIndex + 1 }}</div>
                        <ul class="mb-0">
                            @foreach (($entry['points'] ?? []) as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            @endif
        </div>
    @endif

    <div class="tour-wizard-layout">
        <div class="card tour-stepper">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div class="fw-semibold">Progreso</div>
                    <span class="badge text-bg-primary">{{ $progress }}%</span>
                </div>
                <div class="progress mb-3" style="height: .5rem;">
                    <div class="progress-bar" style="width: {{ $progress }}%"></div>
                </div>
                <div class="vstack gap-1">
                    @foreach ($steps as $number => $label)
                        @php
                            $canJump = ! $isNew && $number <= max($savedStep, $currentStep);
                        @endphp
                        <a class="tour-step-item {{ $number === $currentStep ? 'is-active' : '' }} {{ ! $isNew && $number < $savedStep ? 'is-done' : '' }}" href="{{ $canJump ? route('tours.wizard.edit', [$tour, 'step' => $number]) : '#' }}">
                            <span class="tour-step-number">{{ $number }}</span>
                            <span>
                                <span class="tour-step-title d-block">{{ $label }}</span>
                                <span class="tour-step-status">{{ ! $isNew && $number < $savedStep ? 'Guardado' : ($number === $currentStep ? 'Actual' : 'Pendiente') }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card tour-wizard-card">
            <div class="card-header">
                <div>
                    <h3 class="card-title mb-1">Paso {{ $currentStep }}: {{ $steps[$currentStep] }}</h3>
                    <div class="text-body-secondary small">{{ $isNew ? 'Selecciona la categoria para iniciar el borrador.' : 'Puedes guardar y continuar luego desde este punto.' }}</div>
                </div>
                @if ($tour)
                    <span class="badge text-bg-{{ $tour->status === \App\Models\Tour::STATUS_DRAFT ? 'warning' : 'success' }}">{{ $tour->status_label }}</span>
                @endif
            </div>

            <form method="POST" action="{{ $isNew ? route('tours.draft.store') : route('tours.wizard.step', [$tour, $currentStep]) }}" enctype="multipart/form-data" autocomplete="off" novalidate>
                @csrf
                @unless($isNew)
                    @method('PATCH')
                @endunless
                <input type="hidden" name="step" value="{{ $currentStep }}">

                <div class="card-body">
                    @if ($currentStep === 1)
                        @if (\App\Support\CompanyContext::id() === null && $isNew)
                            <div class="mb-3">
                                <label class="form-label" for="tour-company-id">Empresa</label>
                                <select class="form-select @error('company_id') is-invalid @enderror" id="tour-company-id" name="company_id" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}" @selected((int) old('company_id') === (int) $company->id)>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                                @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="tour-category-id">Categoria del tour</label>
                                <select class="form-select @error('category_id') is-invalid @enderror" id="tour-category-id" name="category_id" required data-category-select>
                                    <option value="">Seleccionar categoria</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" data-description="{{ $category->description }}" @selected((int) old('category_id', $tour->category_id ?? 0) === (int) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                                @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-reference-code">Codigo de referencia</label>
                                <input class="form-control @error('reference_code') is-invalid @enderror" id="tour-reference-code" name="reference_code" value="{{ old('reference_code', $tour?->reference_code) }}" placeholder="Automatico si se deja vacio">
                                @error('reference_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-12">
                                <label class="form-label" for="tour-title">Titulo principal del tour</label>
                                <input class="form-control @error('title') is-invalid @enderror" id="tour-title" name="title" value="{{ old('title', $tour?->title) }}" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                        <div class="alert alert-info mt-3 mb-0" data-category-description>Selecciona una categoria para ver su descripcion.</div>
                    @elseif ($currentStep === 2)
                        <div class="mb-3">
                            <label class="form-label" for="tour-short-description">Descripcion breve</label>
                            <textarea class="form-control @error('short_description') is-invalid @enderror" id="tour-short-description" name="short_description" rows="5" data-counter data-min="200">{{ old('short_description', $tour->short_description) }}</textarea>
                            <div class="form-hint"><span data-counter-output>0</span> caracteres. Minimo 200.</div>
                            @error('short_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="tour-full-description">Descripcion completa</label>
                            <textarea class="form-control @error('full_description') is-invalid @enderror" id="tour-full-description" name="full_description" rows="8" maxlength="2000" data-counter data-min="500">{{ old('full_description', $tour->full_description) }}</textarea>
                            <div class="form-hint"><span data-counter-output>0</span> caracteres. Minimo 500, maximo 2000.</div>
                            @error('full_description')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    @elseif ($currentStep === 3)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="tour-city">Ciudad</label>
                                <input type="hidden" name="city" value="{{ old('city', $tour->city) }}" data-location-city-value>
                                <input class="form-control @error('city') is-invalid @enderror" id="tour-city" value="{{ old('city', $tour->city) }}" required data-location-city data-location-search-url="{{ route('locations.search') }}">
                                @error('city')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-country">Pais</label>
                                <input class="form-control @error('country') is-invalid @enderror" id="tour-country" name="country" value="{{ old('country', $tour->country) }}" required readonly data-location-country>
                                @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @elseif ($currentStep === 4)
                        <label class="form-label" for="keyword-input">Keywords</label>
                        <input type="hidden" name="keywords" value="{{ $keywordsValue }}" data-keywords-value>
                        <div class="input-group mb-2">
                            <input class="form-control" id="keyword-input" data-keyword-input placeholder="Escribe una palabra y presiona Enter">
                            <button class="btn btn-outline-primary" type="button" data-keyword-add>Agregar</button>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mb-2" data-keyword-list></div>
                        <div class="form-hint">Minimo 5 y maximo 20 palabras clave.</div>
                        @error('keywords')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @elseif ($currentStep === 5)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="tour-includes">Lo que incluye</label>
                                <textarea class="form-control @error('includes') is-invalid @enderror" id="tour-includes" name="includes" rows="7">{{ old('includes', $tour->includes ?: $tour->included) }}</textarea>
                                @error('includes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-excludes">Lo que no incluye</label>
                                <textarea class="form-control @error('excludes') is-invalid @enderror" id="tour-excludes" name="excludes" rows="7">{{ old('excludes', $tour->excludes ?: $tour->not_included) }}</textarea>
                                @error('excludes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @elseif ($currentStep === 6)
                        <div class="mb-4">
                            <label class="form-label" for="tour-guide-type-id">Tipo de guia</label>
                            <select class="form-select @error('guide_type_id') is-invalid @enderror" id="tour-guide-type-id" name="guide_type_id" required>
                                <option value="">Seleccionar tipo de guia</option>
                                @foreach ($guideTypes as $guideType)
                                    <option value="{{ $guideType->id }}" @selected((int) old('guide_type_id', $tour->guide_type_id) === (int) $guideType->id)>{{ $guideType->title }}</option>
                                @endforeach
                            </select>
                            @error('guide_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Incluye comida</label>
                                <div class="form-selectgroup mb-3">
                                    <label class="form-selectgroup-item"><input class="form-selectgroup-input" type="radio" name="includes_food" value="1" data-food-toggle @checked(old('includes_food', $tour->includes_food) == 1)><span class="form-selectgroup-label">Si</span></label>
                                    <label class="form-selectgroup-item"><input class="form-selectgroup-input" type="radio" name="includes_food" value="0" data-food-toggle @checked(old('includes_food', $tour->includes_food) == 0)><span class="form-selectgroup-label">No</span></label>
                                </div>
                                <div data-food-details>
                                    <label class="form-label" for="tour-food-details">Detalle de comida incluida</label>
                                    <textarea class="form-control @error('food_details') is-invalid @enderror" id="tour-food-details" name="food_details" rows="5">{{ old('food_details', $tour->food_details) }}</textarea>
                                    @error('food_details')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Incluye transporte</label>
                                <div class="form-selectgroup mb-3">
                                    <label class="form-selectgroup-item"><input class="form-selectgroup-input" type="radio" name="includes_transport" value="1" data-transport-toggle @checked(old('includes_transport', $tour->includes_transport) == 1)><span class="form-selectgroup-label">Si</span></label>
                                    <label class="form-selectgroup-item"><input class="form-selectgroup-input" type="radio" name="includes_transport" value="0" data-transport-toggle @checked(old('includes_transport', $tour->includes_transport) == 0)><span class="form-selectgroup-label">No</span></label>
                                </div>
                                <div data-transport-details>
                                    <label class="form-label" for="tour-transport-type-id">Tipo de transporte</label>
                                    <select class="form-select @error('transport_type_id') is-invalid @enderror" id="tour-transport-type-id" name="transport_type_id">
                                        <option value="">Seleccionar tipo de transporte</option>
                                        @foreach ($transportTypes as $transportType)
                                            <option value="{{ $transportType->id }}" @selected((int) old('transport_type_id', $tour->transport_type_id) === (int) $transportType->id)>{{ $transportType->title }}</option>
                                        @endforeach
                                    </select>
                                    @error('transport_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    @elseif ($currentStep === 7)
                        <div class="row g-3">
                            <div class="col-md-6">
                                <input type="hidden" name="pets_allowed" value="0">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" name="pets_allowed" type="checkbox" value="1" @checked(old('pets_allowed', $tour->pets_allowed) == 1)>
                                    <span class="form-check-label">Se permiten mascotas</span>
                                </label>
                                @error('pets_allowed')<div class="text-danger small">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6"><label class="form-label" for="tour-prohibitions">Prohibiciones</label><textarea class="form-control" id="tour-prohibitions" name="prohibitions" rows="4">{{ old('prohibitions', $tour->prohibitions) }}</textarea></div>
                            <div class="col-md-6"><label class="form-label" for="tour-recommendations">Recomendaciones</label><textarea class="form-control" id="tour-recommendations" name="recommendations" rows="4">{{ old('recommendations', $tour->recommendations) }}</textarea></div>
                            <div class="col-md-6"><label class="form-label" for="tour-emergency-phone">Numero de emergencia</label><input class="form-control @error('emergency_phone') is-invalid @enderror" id="tour-emergency-phone" name="emergency_phone" value="{{ old('emergency_phone', $tour->emergency_phone) }}">@error('emergency_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                        </div>
                    @elseif ($currentStep === 8)
                        <label class="form-label" for="tour-images">Imagenes del tour</label>
                        <input class="form-control @error('images') is-invalid @enderror" id="tour-images" name="images[]" type="file" multiple accept="image/jpeg,image/png,image/webp" data-image-preview-input>
                        <div class="form-hint mb-3">JPG, JPEG, PNG o WebP. Minimo 5 imagenes para finalizar.</div>
                        <div class="tour-image-grid mb-3" data-image-preview></div>
                        @error('images')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @if ($tour->images->isNotEmpty())
                            <div class="tour-image-grid">
                                @foreach ($tour->images as $image)
                                    <div class="tour-image-card">
                                        <img src="{{ $image->url }}" alt="{{ $image->original_name ?: 'Imagen de tour' }}">
                                        <div class="p-2">
                                            @if ($image->is_main)
                                                <span class="badge text-bg-primary mb-2">Principal</span>
                                            @endif
                                            <div class="d-flex gap-1">
                                                <button class="btn btn-outline-primary btn-sm flex-fill" type="submit" form="tour-image-main-{{ $image->id }}">Principal</button>
                                                <button class="btn btn-outline-danger btn-sm flex-fill" type="submit" form="tour-image-delete-{{ $image->id }}">Eliminar</button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    @elseif ($currentStep === 9)
                        <div class="row g-3" data-tour-operation>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-activity-type">Tipo de actividad</label>
                                <select class="form-select @error('activity_type') is-invalid @enderror" id="tour-activity-type" name="activity_type">
                                    <option value="">Seleccionar</option>
                                    <option value="private" @selected(old('activity_type', $tour->activity_type) === 'private')>Privada</option>
                                    <option value="shared" @selected(old('activity_type', $tour->activity_type) === 'shared')>Compartida</option>
                                </select>
                                @error('activity_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-capacity">Cantidad de cupos</label>
                                <input class="form-control @error('capacity') is-invalid @enderror" id="tour-capacity" name="capacity" type="number" min="1" max="99999" value="{{ old('capacity', $tour->capacity) }}">
                                @error('capacity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="tour-meeting-point">Punto de recogida</label>
                                <input class="form-control @error('meeting_point') is-invalid @enderror" id="tour-meeting-point" name="meeting_point" value="{{ old('meeting_point', $tour->meeting_point) }}">
                                @error('meeting_point')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="tour-booking-deadline-unit">Limite de reserva</label>
                                <select class="form-select @error('booking_deadline_unit') is-invalid @enderror" id="tour-booking-deadline-unit" name="booking_deadline_unit" data-booking-unit>
                                    <option value="">Seleccionar</option>
                                    <option value="hours" @selected(old('booking_deadline_unit', $tour->booking_deadline_unit) === 'hours')>Horas antes</option>
                                    <option value="days" @selected(old('booking_deadline_unit', $tour->booking_deadline_unit) === 'days')>Dias antes</option>
                                </select>
                                @error('booking_deadline_unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="tour-booking-deadline-value" data-booking-value-label>Cantidad</label>
                                <input class="form-control @error('booking_deadline_value') is-invalid @enderror" id="tour-booking-deadline-value" name="booking_deadline_value" type="number" min="1" value="{{ old('booking_deadline_value', $tour->booking_deadline_value) }}" data-booking-value>
                                @error('booking_deadline_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    @elseif ($currentStep === 10)
                        <div data-itinerary-builder>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                                <div>
                                    <div class="fw-semibold">Itinerario dia a dia</div>
                                    <div class="text-body-secondary small">Organiza cada dia y sus actividades en orden.</div>
                                </div>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-add-day>Agregar dia</button>
                            </div>

                            <div class="vstack gap-3" data-itinerary-days>
                                @foreach ($itineraryDays as $dayIndex => $day)
                                    <div class="itinerary-day" data-itinerary-day>
                                        <div class="itinerary-day-header">
                                            <div class="fw-semibold" data-day-label>Dia {{ $dayIndex + 1 }}</div>
                                            <button class="btn btn-outline-danger btn-sm" type="button" data-remove-day>Eliminar dia</button>
                                        </div>
                                        <div class="p-3">
                                            <input type="hidden" name="itinerary_days[{{ $dayIndex }}][day_number]" value="{{ $dayIndex + 1 }}" data-day-number>
                                            <div class="row g-3 mb-3">
                                                <div class="col-md-12">
                                                    <label class="form-label">Titulo del dia</label>
                                                    <input class="form-control @error('itinerary_days.'.$dayIndex.'.title') is-invalid @enderror" name="itinerary_days[{{ $dayIndex }}][title]" value="{{ $day['title'] ?? '' }}" required>
                                                    @error('itinerary_days.'.$dayIndex.'.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                </div>
                                                <div class="col-md-12">
                                                    <label class="form-label">Resumen del dia</label>
                                                    <textarea class="form-control" name="itinerary_days[{{ $dayIndex }}][summary]" rows="3">{{ $day['summary'] ?? '' }}</textarea>
                                                </div>
                                            </div>

                                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                                <div class="fw-semibold small text-uppercase text-body-secondary">Actividades del dia</div>
                                                <button class="btn btn-outline-primary btn-sm" type="button" data-add-stop>Agregar actividad</button>
                                            </div>

                                            <div class="vstack gap-2" data-itinerary-stops>
                                                @foreach (($day['stops'] ?? []) as $stopIndex => $stop)
                                                    <div class="itinerary-stop" data-itinerary-stop>
                                                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                                            <div class="fw-semibold" data-stop-label>Actividad {{ $stopIndex + 1 }}</div>
                                                            <button class="btn btn-outline-danger btn-sm" type="button" data-remove-stop>Eliminar</button>
                                                        </div>
                                                        <input type="hidden" name="itinerary_days[{{ $dayIndex }}][stops][{{ $stopIndex }}][position]" value="{{ $stopIndex + 1 }}" data-stop-position>
                                                        <div class="row g-3">
                                                            <div class="col-md-4">
                                                                <label class="form-label">Tipo</label>
                                                                <select class="form-select @error('itinerary_days.'.$dayIndex.'.stops.'.$stopIndex.'.activity_type_id') is-invalid @enderror" name="itinerary_days[{{ $dayIndex }}][stops][{{ $stopIndex }}][activity_type_id]" required>
                                                                    <option value="">Seleccionar</option>
                                                                    @foreach ($activityTypes as $activityType)
                                                                        <option value="{{ $activityType->id }}" @selected((int) ($stop['activity_type_id'] ?? 0) === (int) $activityType->id)>{{ $activityType->title }}</option>
                                                                    @endforeach
                                                                </select>
                                                                @error('itinerary_days.'.$dayIndex.'.stops.'.$stopIndex.'.activity_type_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Hora</label>
                                                                <input class="form-control" name="itinerary_days[{{ $dayIndex }}][stops][{{ $stopIndex }}][start_time]" type="time" step="300" value="{{ $stop['start_time'] ?? '' }}">
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label">Titulo</label>
                                                                <input class="form-control @error('itinerary_days.'.$dayIndex.'.stops.'.$stopIndex.'.title') is-invalid @enderror" name="itinerary_days[{{ $dayIndex }}][stops][{{ $stopIndex }}][title]" value="{{ $stop['title'] ?? '' }}" required>
                                                                @error('itinerary_days.'.$dayIndex.'.stops.'.$stopIndex.'.title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                                            </div>
                                                            <div class="col-md-12">
                                                                <label class="form-label">Ubicacion</label>
                                                                <input class="form-control" name="itinerary_days[{{ $dayIndex }}][stops][{{ $stopIndex }}][location_name]" value="{{ $stop['location_name'] ?? '' }}">
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            @error('itinerary_days')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                        </div>
                    @endif
                </div>

                <div class="card-footer d-flex flex-wrap justify-content-between gap-2">
                    <a class="btn btn-outline-secondary" href="{{ route('tours.index') }}">Salir</a>
                    <div class="d-flex flex-wrap gap-2">
                        @if (! $isNew && $currentStep > 1)
                            <a class="btn btn-outline-secondary" href="{{ route('tours.wizard.edit', [$tour, 'step' => $currentStep - 1]) }}">Anterior</a>
                        @endif
                        @if (! $isNew)
                            <button class="btn btn-outline-primary" type="submit" name="action" value="draft">Guardar borrador</button>
                        @endif
                        @if ($currentStep < \App\Models\Tour::TOTAL_STEPS)
                            <button class="btn btn-primary" type="submit" name="action" value="next">{{ $isNew ? 'Iniciar registro' : 'Siguiente' }}</button>
                        @elseif ($tour)
                            <button class="btn btn-success" type="submit" name="action" value="finalize">Finalizar</button>
                        @endif
                    </div>
                </div>
            </form>
            @if ($tour)
                @foreach ($tour->images as $image)
                    <form id="tour-image-main-{{ $image->id }}" method="POST" action="{{ route('tours.images.main', [$tour, $image]) }}">
                        @csrf
                        @method('PATCH')
                    </form>
                    <form id="tour-image-delete-{{ $image->id }}" method="POST" action="{{ route('tours.images.destroy', [$tour, $image]) }}">
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const categorySelect = document.querySelector('[data-category-select]');
            const categoryDescription = document.querySelector('[data-category-description]');
            const syncCategoryDescription = () => {
                if (!categorySelect || !categoryDescription) return;
                const option = categorySelect.selectedOptions[0];
                categoryDescription.textContent = option?.dataset.description || 'Selecciona una categoria para ver su descripcion.';
            };
            categorySelect?.addEventListener('change', syncCategoryDescription);
            syncCategoryDescription();

            document.querySelectorAll('[data-counter]').forEach((field) => {
                const output = field.parentElement.querySelector('[data-counter-output]');
                const update = () => output && (output.textContent = field.value.length);
                field.addEventListener('input', update);
                update();
            });

            const keywordInput = document.querySelector('[data-keyword-input]');
            const keywordValue = document.querySelector('[data-keywords-value]');
            const keywordList = document.querySelector('[data-keyword-list]');
            let keywords = keywordValue?.value ? keywordValue.value.split(',').map((item) => item.trim()).filter(Boolean) : [];
            const renderKeywords = () => {
                if (!keywordList || !keywordValue) return;
                keywordValue.value = keywords.join(',');
                keywordList.innerHTML = keywords.map((keyword, index) => `<span class="keyword-chip">${keyword}<button type="button" data-keyword-remove="${index}" aria-label="Quitar ${keyword}">x</button></span>`).join('');
            };
            const addKeyword = () => {
                const value = keywordInput?.value.trim();
                if (!value || keywords.length >= 20 || keywords.map((item) => item.toLowerCase()).includes(value.toLowerCase())) return;
                keywords.push(value);
                keywordInput.value = '';
                renderKeywords();
            };
            document.querySelector('[data-keyword-add]')?.addEventListener('click', addKeyword);
            keywordInput?.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addKeyword();
                }
            });
            keywordList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-keyword-remove]');
                if (!button) return;
                keywords.splice(Number(button.dataset.keywordRemove), 1);
                renderKeywords();
            });
            renderKeywords();

            const syncToggle = (selector, targetSelector) => {
                const target = document.querySelector(targetSelector);
                const selected = document.querySelector(`${selector}:checked`);
                if (target) target.classList.toggle('d-none', selected?.value !== '1');
            };
            document.querySelectorAll('[data-food-toggle]').forEach((input) => input.addEventListener('change', () => syncToggle('[data-food-toggle]', '[data-food-details]')));
            document.querySelectorAll('[data-transport-toggle]').forEach((input) => input.addEventListener('change', () => syncToggle('[data-transport-toggle]', '[data-transport-details]')));
            syncToggle('[data-food-toggle]', '[data-food-details]');
            syncToggle('[data-transport-toggle]', '[data-transport-details]');

            const operation = document.querySelector('[data-tour-operation]');
            if (operation) {
                const bookingUnit = operation.querySelector('[data-booking-unit]');
                const bookingValue = operation.querySelector('[data-booking-value]');
                const bookingValueLabel = operation.querySelector('[data-booking-value-label]');

                const syncBookingDeadline = () => {
                    const unit = bookingUnit?.value;

                    if (bookingValue) {
                        bookingValue.disabled = !unit;
                        bookingValue.max = unit === 'hours' ? '720' : '365';
                        bookingValue.placeholder = unit === 'hours' ? 'Ej. 12' : (unit === 'days' ? 'Ej. 2' : '');
                    }

                    if (bookingValueLabel) {
                        bookingValueLabel.textContent = unit === 'hours'
                            ? 'Horas antes de que empiece'
                            : (unit === 'days' ? 'Dias antes de que empiece' : 'Cantidad');
                    }
                };

                bookingUnit?.addEventListener('change', syncBookingDeadline);

                syncBookingDeadline();
            }

            const itineraryBuilder = document.querySelector('[data-itinerary-builder]');
            if (itineraryBuilder) {
                const activityTypes = @json($activityTypes->map(fn ($type) => ['id' => $type->id, 'title' => $type->title])->values());
                const daysWrap = itineraryBuilder.querySelector('[data-itinerary-days]');
                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
                const activityOptions = (selected = '') => ['<option value="">Seleccionar</option>']
                    .concat(activityTypes.map((type) => `<option value="${type.id}" ${String(selected) === String(type.id) ? 'selected' : ''}>${escapeHtml(type.title)}</option>`))
                    .join('');

                const stopHtml = (dayIndex, stopIndex, values = {}) => `
                    <div class="itinerary-stop" data-itinerary-stop>
                        <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                            <div class="fw-semibold" data-stop-label>Actividad ${stopIndex + 1}</div>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-remove-stop>Eliminar</button>
                        </div>
                        <input type="hidden" name="itinerary_days[${dayIndex}][stops][${stopIndex}][position]" value="${stopIndex + 1}" data-stop-position>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" name="itinerary_days[${dayIndex}][stops][${stopIndex}][activity_type_id]" required>${activityOptions(values.activity_type_id)}</select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hora</label>
                                <input class="form-control" name="itinerary_days[${dayIndex}][stops][${stopIndex}][start_time]" type="time" step="300" value="${escapeHtml(values.start_time || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Titulo</label>
                                <input class="form-control" name="itinerary_days[${dayIndex}][stops][${stopIndex}][title]" value="${escapeHtml(values.title || '')}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">Ubicacion</label>
                                <input class="form-control" name="itinerary_days[${dayIndex}][stops][${stopIndex}][location_name]" value="${escapeHtml(values.location_name || '')}">
                            </div>
                        </div>
                    </div>`;

                const dayHtml = (dayIndex) => `
                    <div class="itinerary-day" data-itinerary-day>
                        <div class="itinerary-day-header">
                            <div class="fw-semibold" data-day-label>Dia ${dayIndex + 1}</div>
                            <button class="btn btn-outline-danger btn-sm" type="button" data-remove-day>Eliminar dia</button>
                        </div>
                        <div class="p-3">
                            <input type="hidden" name="itinerary_days[${dayIndex}][day_number]" value="${dayIndex + 1}" data-day-number>
                            <div class="row g-3 mb-3">
                                <div class="col-md-12">
                                    <label class="form-label">Titulo del dia</label>
                                    <input class="form-control" name="itinerary_days[${dayIndex}][title]" value="Dia ${dayIndex + 1}" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label">Resumen del dia</label>
                                    <textarea class="form-control" name="itinerary_days[${dayIndex}][summary]" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                                <div class="fw-semibold small text-uppercase text-body-secondary">Actividades del dia</div>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-add-stop>Agregar actividad</button>
                            </div>
                            <div class="vstack gap-2" data-itinerary-stops>${stopHtml(dayIndex, 0, { start_time: '08:00', activity_type_id: activityTypes[0]?.id })}</div>
                        </div>
                    </div>`;

                const renumberItinerary = () => {
                    daysWrap?.querySelectorAll('[data-itinerary-day]').forEach((day, dayIndex) => {
                        day.querySelector('[data-day-label]').textContent = `Dia ${dayIndex + 1}`;
                        day.querySelector('[data-day-number]').value = String(dayIndex + 1);
                        day.querySelectorAll('[name]').forEach((field) => {
                            field.name = field.name.replace(/itinerary_days\[\d+]/, `itinerary_days[${dayIndex}]`);
                        });
                        day.querySelectorAll('[data-itinerary-stop]').forEach((stop, stopIndex) => {
                            stop.querySelector('[data-stop-label]').textContent = `Actividad ${stopIndex + 1}`;
                            stop.querySelector('[data-stop-position]').value = String(stopIndex + 1);
                            stop.querySelectorAll('[name]').forEach((field) => {
                                field.name = field.name.replace(/\[stops]\[\d+]/, `[stops][${stopIndex}]`);
                            });
                        });
                    });
                };

                itineraryBuilder.querySelector('[data-add-day]')?.addEventListener('click', () => {
                    const dayIndex = daysWrap?.querySelectorAll('[data-itinerary-day]').length || 0;
                    daysWrap?.insertAdjacentHTML('beforeend', dayHtml(dayIndex));
                    renumberItinerary();
                });

                daysWrap?.addEventListener('click', (event) => {
                    const addStop = event.target.closest('[data-add-stop]');
                    const removeStop = event.target.closest('[data-remove-stop]');
                    const removeDay = event.target.closest('[data-remove-day]');

                    if (addStop) {
                        const day = addStop.closest('[data-itinerary-day]');
                        const dayIndex = Array.from(daysWrap.querySelectorAll('[data-itinerary-day]')).indexOf(day);
                        const stopsWrap = day.querySelector('[data-itinerary-stops]');
                        const stopIndex = stopsWrap.querySelectorAll('[data-itinerary-stop]').length;
                        stopsWrap.insertAdjacentHTML('beforeend', stopHtml(dayIndex, stopIndex, { activity_type_id: activityTypes[0]?.id }));
                    }

                    if (removeStop) {
                        const stopsWrap = removeStop.closest('[data-itinerary-stops]');
                        if (stopsWrap.querySelectorAll('[data-itinerary-stop]').length > 1) {
                            removeStop.closest('[data-itinerary-stop]').remove();
                        }
                    }

                    if (removeDay && daysWrap.querySelectorAll('[data-itinerary-day]').length > 1) {
                        removeDay.closest('[data-itinerary-day]').remove();
                    }

                    if (addStop || removeStop || removeDay) {
                        renumberItinerary();
                    }
                });

                renumberItinerary();
            }

            document.querySelector('[data-image-preview-input]')?.addEventListener('change', (event) => {
                const target = document.querySelector('[data-image-preview]');
                if (!target) return;
                target.innerHTML = '';
                Array.from(event.target.files || []).forEach((file) => {
                    const reader = new FileReader();
                    reader.onload = () => {
                        target.insertAdjacentHTML('beforeend', `<div class="tour-image-card"><img src="${reader.result}" alt="${file.name}"><div class="p-2 small text-truncate">${file.name}</div></div>`);
                    };
                    reader.readAsDataURL(file);
                });
            });
        });
    </script>
@endpush
