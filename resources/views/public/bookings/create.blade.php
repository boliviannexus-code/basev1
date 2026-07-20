@extends('layouts.public')

@section('title', 'Reservar '.$tour->display_title)

@section('content')
<section class="public-section">
    <div class="container-xl booking-flow">
        <div>
            <a class="public-back" href="{{ route('public.tours.show', $tour) }}"><i class="ti ti-arrow-left"></i>Volver al tour</a>
            <h1>Completa tu reserva</h1>
            <p class="text-muted">Revisa los datos del viaje y confirma tu cupo. No se realizara cobro en linea.</p>

            <form class="booking-form" action="{{ route('public.bookings.store', $tour) }}" method="POST">
                @csrf
                <div class="form-section">
                    <h2>Fecha y personas</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="travel_date">Fecha</label>
                            <select class="form-select @error('travel_date') is-invalid @enderror" id="travel_date" name="travel_date" required>
                                @foreach ($tour->availabilities as $availability)
                                    <option value="{{ $availability->date->toDateString() }}" @selected(old('travel_date', $travelDate) === $availability->date->toDateString())>
                                        {{ $availability->date->translatedFormat('d M Y') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('travel_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="people">Personas</label>
                            <input class="form-control @error('people') is-invalid @enderror" id="people" name="people" type="number" min="1" value="{{ old('people', $people) }}" required>
                            @error('people')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h2>Datos del turista</h2>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="first_name">Nombre</label>
                            <input class="form-control @error('first_name') is-invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $firstName) }}" required>
                            @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="last_name">Apellido</label>
                            <input class="form-control @error('last_name') is-invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $lastName) }}" required>
                            @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="email">Email</label>
                            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', auth()->user()->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="phone">Telefono</label>
                            <input class="form-control @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required>
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="country">Pais</label>
                            <input type="hidden" name="country" value="{{ old('country') }}" data-location-country-value>
                            <input class="form-control @error('country') is-invalid @enderror" id="country" value="{{ old('country') }}" required data-location-country-picker data-location-search-url="{{ route('locations.search') }}">
                            @error('country')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="special_requirements">Comentarios o requerimientos especiales</label>
                            <textarea class="form-control @error('special_requirements') is-invalid @enderror" id="special_requirements" name="special_requirements" rows="4">{{ old('special_requirements') }}</textarea>
                            @error('special_requirements')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <button class="btn btn-primary btn-lg" type="submit">Confirmar reserva</button>
            </form>
        </div>

        <aside class="booking-summary">
            <h2>Resumen</h2>
            <strong>{{ $tour->display_title }}</strong>
            <span>{{ $tour->city ?: $tour->country }}</span>
            <hr>
            <div><span>Fecha</span><strong>{{ $travelDate ? \Illuminate\Support\Carbon::parse($travelDate)->translatedFormat('d M Y') : 'Selecciona una fecha' }}</strong></div>
            <div><span>Personas</span><strong>{{ $people }}</strong></div>
            <div><span>Precio por persona</span><strong>{{ $quote ? '$'.number_format($quote['unit_price'], 2) : 'Por confirmar' }}</strong></div>
            <div class="summary-total"><span>Total a pagar</span><strong>{{ $quote ? '$'.number_format($quote['total'], 2) : 'Por confirmar' }}</strong></div>
            <p>No se cobrara en linea. El operador confirmara cualquier instruccion adicional por email o telefono.</p>
        </aside>
    </div>
</section>
@endsection
