@extends('layouts.public')

@section('title', $tour->display_title.' | '.config('app.name', 'Tours'))

@section('content')
@php
    $mainImage = $tour->images->first()?->url;
    $price = $tour->prices->min('price_usd');
    $rating = $tour->reviews_avg_rating ? number_format((float) $tour->reviews_avg_rating, 1) : null;
@endphp

<section class="tour-detail-hero">
    <div class="container-xl">
        <div class="tour-gallery">
            <div class="tour-gallery-main">
                @if ($mainImage)
                    <img src="{{ $mainImage }}" alt="{{ $tour->display_title }}">
                @else
                    <div class="tour-placeholder" role="img" aria-label="Paisaje turistico de referencia"><i class="ti ti-mountain"></i></div>
                @endif
            </div>
            @foreach ($tour->images->skip(1)->take(4) as $image)
                <img src="{{ $image->url }}" alt="Imagen de {{ $tour->display_title }}">
            @endforeach
        </div>
    </div>
</section>

<section class="public-section pt-4">
    <div class="container-xl detail-layout">
        <article class="detail-content">
            <div class="tour-kicker">
                <span><i class="ti ti-map-pin"></i>{{ $tour->location_text ?: trim(($tour->city ? $tour->city.', ' : '').$tour->country) }}</span>
                <span><i class="ti ti-star-filled text-warning"></i>{{ $rating ? $rating.' ('.$tour->reviews_count.' opiniones)' : 'Nuevo' }}</span>
            </div>
            <h1>{{ $tour->display_title }}</h1>
            <p class="lead">{{ $tour->short_description ?: $tour->description }}</p>

            <div class="quick-facts">
                <div><i class="ti ti-clock"></i><strong>Duracion</strong><span>{{ $tour->duration ?: 'Por confirmar' }}</span></div>
                <div><i class="ti ti-user-star"></i><strong>Guia</strong><span>{{ $tour->guideType->title ?? 'Guia local' }}</span></div>
                <div><i class="ti ti-map-2"></i><strong>Ubicacion</strong><span>{{ $tour->city ?: $tour->country ?: 'Destino' }}</span></div>
            </div>

            <h2>Descripcion completa</h2>
            <div class="prose">{!! nl2br(e($tour->full_description ?: $tour->description ?: 'El operador esta completando la descripcion de esta experiencia.')) !!}</div>

            <div class="include-grid">
                <section>
                    <h2>Que incluye</h2>
                    <div class="prose">{!! nl2br(e($tour->includes ?: $tour->included ?: 'Consulta los detalles incluidos antes de reservar.')) !!}</div>
                </section>
                <section>
                    <h2>Que no incluye</h2>
                    <div class="prose">{!! nl2br(e($tour->excludes ?: $tour->not_included ?: 'Gastos personales y servicios no mencionados.')) !!}</div>
                </section>
            </div>

            <h2>Itinerario</h2>
            @forelse ($tour->itineraryDays as $day)
                <div class="itinerary-day">
                    <strong>Dia {{ $day->day_number }}: {{ $day->title }}</strong>
                    @if ($day->summary)<p>{{ $day->summary }}</p>@endif
                    @foreach ($day->stops as $stop)
                        <div class="itinerary-stop">
                            <span>{{ $stop->start_time ? \Illuminate\Support\Carbon::parse($stop->start_time)->format('H:i') : 'Horario flexible' }}</span>
                            <div>{{ $stop->title }} @if($stop->location_name)<small>{{ $stop->location_name }}</small>@endif</div>
                        </div>
                    @endforeach
                </div>
            @empty
                <div class="public-empty compact">El itinerario se confirmara con el operador del tour.</div>
            @endforelse

            <h2>Punto de encuentro</h2>
            <p>{{ $tour->meeting_point ?: 'El punto de encuentro se coordinara en la confirmacion.' }}</p>

            <h2>Politicas de cancelacion</h2>
            <p>Cancelacion gratuita hasta 24 horas antes del inicio, salvo condiciones especiales indicadas por el operador.</p>

            <h2>Opiniones de clientes</h2>
            @forelse ($tour->reviews as $review)
                <div class="review-item">
                    <strong>{{ $review->title ?: $review->user->name }}</strong>
                    <span>{{ str_repeat('★', $review->rating) }}</span>
                    <p>{{ $review->comment }}</p>
                </div>
            @empty
                <div class="public-empty compact">Este tour aun no tiene opiniones. Puedes ser de los primeros en vivirlo.</div>
            @endforelse
        </article>

        <aside class="booking-box">
            <span class="text-muted">Desde</span>
            <div class="booking-price">{{ $price ? '$'.number_format((float) $price, 2) : 'Consultar' }} <small>por persona</small></div>
            <form action="{{ route('public.bookings.create', $tour) }}" method="GET">
                <label class="form-label" for="date">Fecha</label>
                <select class="form-select mb-3" id="date" name="date" required>
                    @foreach ($tour->availabilities as $availability)
                        <option value="{{ $availability->date->toDateString() }}">
                            {{ $availability->date->translatedFormat('d M Y') }}
                            @if ($availability->capacity !== null)
                                - {{ max(0, $availability->capacity - $availability->booked_count) }} cupos
                            @endif
                        </option>
                    @endforeach
                </select>
                <label class="form-label" for="people">Personas</label>
                <input class="form-control mb-3" id="people" name="people" type="number" min="1" value="2" required>
                <button class="btn btn-primary w-100 btn-lg" type="submit">Reservar ahora</button>
            </form>
        </aside>
    </div>
</section>
@endsection
