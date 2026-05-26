@props(['tour'])
@php
    $image = $tour->images->first()?->url;
    $price = $tour->prices->min('price_usd');
    $rating = $tour->reviews_avg_rating ? number_format((float) $tour->reviews_avg_rating, 1) : null;
@endphp

<article class="tour-card">
    <a class="tour-card-media" href="{{ route('public.tours.show', $tour) }}" aria-label="Ver {{ $tour->display_title }}">
        @if ($image)
            <img src="{{ $image }}" alt="{{ $tour->display_title }}">
        @else
            <div class="tour-placeholder" role="img" aria-label="Paisaje turistico de referencia">
                <i class="ti ti-mountain"></i>
            </div>
        @endif
    </a>
    <div class="tour-card-body">
        <div class="d-flex justify-content-between gap-2">
            <span class="tour-card-location"><i class="ti ti-map-pin"></i>{{ $tour->city ?: $tour->country ?: 'Destino por confirmar' }}</span>
            <span class="tour-card-rating">
                <i class="ti ti-star-filled"></i>{{ $rating ?? 'Nuevo' }}
            </span>
        </div>
        <h3><a href="{{ route('public.tours.show', $tour) }}">{{ $tour->display_title }}</a></h3>
        <p>{{ str($tour->short_description ?: $tour->description ?: 'Experiencia local con cupos limitados.')->limit(96) }}</p>
        <div class="tour-card-meta">
            <span><i class="ti ti-clock"></i>{{ $tour->duration ?: 'Duracion flexible' }}</span>
            @if ($tour->category)
                <span><i class="ti ti-tag"></i>{{ $tour->category->name }}</span>
            @endif
        </div>
        <div class="tour-card-footer">
            <div>
                <span class="text-muted small">Desde</span>
                <strong>{{ $price ? '$'.number_format((float) $price, 2) : 'Consultar' }}</strong>
            </div>
            <a class="btn btn-primary btn-sm" href="{{ route('public.tours.show', $tour) }}">Ver disponibilidad</a>
        </div>
    </div>
</article>
