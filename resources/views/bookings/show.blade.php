@extends('layouts.admin')

@section('title', 'Reserva '.$booking->booking_code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Detalle de reserva')
@section('page-subtitle', $booking->booking_code)

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <x-ui.card title="Informacion de la reserva">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Codigo</dt>
                    <dd class="col-sm-9">{{ $booking->booking_code }}</dd>

                    <dt class="col-sm-3">Tour</dt>
                    <dd class="col-sm-9">{{ $booking->tour?->display_title ?? 'Tour eliminado' }}</dd>

                    <dt class="col-sm-3">Empresa</dt>
                    <dd class="col-sm-9">{{ $booking->tour?->company?->name ?? '-' }}</dd>

                    <dt class="col-sm-3">Fecha</dt>
                    <dd class="col-sm-9">{{ $booking->travel_date?->format('d/m/Y') }}</dd>

                    <dt class="col-sm-3">Personas</dt>
                    <dd class="col-sm-9">{{ $booking->people }}</dd>

                    <dt class="col-sm-3">Precio unitario</dt>
                    <dd class="col-sm-9">${{ number_format((float) $booking->unit_price_usd, 2) }}</dd>

                    <dt class="col-sm-3">Total</dt>
                    <dd class="col-sm-9 fw-bold">${{ number_format((float) $booking->total_usd, 2) }}</dd>

                    <dt class="col-sm-3">Estado</dt>
                    <dd class="col-sm-9"><x-public.booking-status :status="$booking->status" /></dd>
                </dl>
            </x-ui.card>

            <x-ui.card class="mt-3" title="Datos del turista">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Nombre</dt>
                    <dd class="col-sm-9">{{ $booking->first_name }} {{ $booking->last_name }}</dd>

                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9">{{ $booking->email }}</dd>

                    <dt class="col-sm-3">Telefono</dt>
                    <dd class="col-sm-9">{{ $booking->phone }}</dd>

                    <dt class="col-sm-3">Pais</dt>
                    <dd class="col-sm-9">{{ $booking->country }}</dd>

                    <dt class="col-sm-3">Comentarios</dt>
                    <dd class="col-sm-9">{{ $booking->special_requirements ?: '-' }}</dd>
                </dl>
            </x-ui.card>
        </div>

        <div class="col-lg-4">
            <x-ui.card title="Gestion">
                @can('bookings.manage')
                    <form method="POST" action="{{ route('bookings.status.update', $booking) }}">
                        @csrf
                        @method('PATCH')
                        <label class="form-label" for="status">Estado de reserva</label>
                        <select class="form-select mb-3" id="status" name="status">
                            @foreach ($statuses as $status => $label)
                                <option value="{{ $status }}" @selected($booking->status === $status)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button class="btn btn-primary w-100" type="submit">Actualizar estado</button>
                    </form>
                @else
                    <p class="text-muted mb-0">No tienes permiso para modificar el estado.</p>
                @endcan
            </x-ui.card>

            <a class="btn btn-outline-secondary w-100 mt-3" href="{{ route('bookings.index') }}">Volver al listado</a>
        </div>
    </div>
@endsection
