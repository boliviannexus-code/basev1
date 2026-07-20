@extends('layouts.admin')

@section('title', 'Reservas | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reservas')
@section('page-subtitle', 'Seguimiento y gestion de reservas de turistas')

@section('content')
    <x-ui.table-card title="Listado de reservas">
        <form class="row g-2 align-items-end mb-3" method="GET" action="{{ route('bookings.index') }}">
            <div class="col-md-3">
                <label class="form-label" for="q">Buscar</label>
                <input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Codigo, turista, email o tour">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="status">Estado</label>
                <select class="form-select" id="status" name="status">
                    <option value="">Todos</option>
                    @foreach ($statuses as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_from">Desde</label>
                <input class="form-control" id="date_from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" for="date_to">Hasta</label>
                <input class="form-control" id="date_to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit"><i class="ti ti-search me-1"></i>Filtrar</button>
                <a class="btn btn-outline-secondary" href="{{ route('bookings.index') }}">Limpiar</a>
            </div>
        </form>

        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Tour</th>
                    <th>Turista</th>
                    <th>Fecha</th>
                    <th>Personas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($bookings as $booking)
                    <tr>
                        <td class="fw-semibold">{{ $booking->booking_code }}</td>
                        <td>
                            <div class="fw-semibold">{{ $booking->tour?->display_title ?? 'Tour eliminado' }}</div>
                            <div class="text-muted small">{{ $booking->tour?->company?->name ?? '-' }}</div>
                        </td>
                        <td>
                            <div>{{ $booking->first_name }} {{ $booking->last_name }}</div>
                            <div class="text-muted small">{{ $booking->email }}</div>
                        </td>
                        <td>{{ $booking->travel_date?->format('d/m/Y') }}</td>
                        <td>{{ $booking->people }}</td>
                        <td>${{ number_format((float) $booking->total_usd, 2) }}</td>
                        <td><x-public.booking-status :status="$booking->status" /></td>
                        <td class="text-end">
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('bookings.show', $booking) }}">Ver detalle</a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="8" message="No hay reservas registradas." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>{{ $bookings->links() }}</x-slot:footer>
    </x-ui.table-card>
@endsection
