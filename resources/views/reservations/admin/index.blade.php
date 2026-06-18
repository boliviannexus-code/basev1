@extends('layouts.admin')

@section('title', 'Reservas entrantes | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reservas entrantes')
@section('page-subtitle', 'Solicitudes publicas, pagos QR en revision y reservas confirmadas')

@section('content')
    @php
        $statusLabels = [
            'pending_payment' => 'Pendientes de pago',
            'payment_under_review' => 'Pagos en revision',
            'confirmed' => 'Confirmadas',
            'checked_in' => 'En check-in',
            'rejected' => 'Rechazadas',
            'cancelled' => 'Canceladas',
            'expired' => 'Vencidas',
        ];
        $statusTones = [
            'pending_payment' => 'warning',
            'payment_under_review' => 'info',
            'confirmed' => 'success',
            'checked_in' => 'primary',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            'expired' => 'secondary',
        ];
    @endphp

    <div class="reservation-status-tabs">
        <a class="reservation-status-tab {{ blank($status) ? 'active' : '' }}" href="{{ route('admin.reservations.index') }}">
            <span>Todas</span>
            <strong>{{ $counts->sum() }}</strong>
        </a>
        @foreach ($statusLabels as $key => $label)
            <a class="reservation-status-tab {{ $status === $key ? 'active' : '' }}" href="{{ route('admin.reservations.index', ['status' => $key]) }}">
                <span>{{ $label }}</span>
                <strong>{{ $counts->get($key, 0) }}</strong>
            </a>
        @endforeach
    </div>

    @if ($reservationGroups->isNotEmpty())
        <x-ui.table-card title="Reservas internas agrupadas" class="mb-3">
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Codigo</th>
                        <th>Huesped</th>
                        <th>Recursos</th>
                        <th>Fechas</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th class="w-1"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($reservationGroups as $group)
                        <tr>
                            <td><a class="fw-bold" href="{{ route('admin.reservation-groups.show', $group) }}">{{ $group->code }}</a></td>
                            <td>
                                <div>{{ $group->guest_name }}</div>
                                <div class="text-muted small">{{ $group->guest_email ?: 'Sin correo' }}</div>
                                <div class="text-muted small">Canal: {{ $group->reservationChannel?->name ?: 'Sin canal' }}</div>
                            </td>
                            <td>
                                <div>{{ $group->reservations->count() }} recurso{{ $group->reservations->count() === 1 ? '' : 's' }}</div>
                                <div class="text-muted small">{{ $group->reservations->pluck('space')->filter()->unique('id')->count() }} alojamiento{{ $group->reservations->pluck('space')->filter()->unique('id')->count() === 1 ? '' : 's' }}</div>
                            </td>
                            <td>
                                <div>{{ $group->check_in->toDateString() }} al {{ $group->check_out->toDateString() }}</div>
                                <div class="text-muted small">{{ $group->nights }} noche{{ $group->nights === 1 ? '' : 's' }} · {{ $group->guests }} persona{{ $group->guests === 1 ? '' : 's' }}</div>
                            </td>
                            <td>
                                <div>{{ money_format_decimal($group->total_amount) }} {{ $group->currency }}</div>
                                <div class="text-muted small">Adelanto {{ money_format_decimal($group->advance_amount) }}</div>
                            </td>
                            <td>
                                <span class="badge bg-{{ $statusTones[$group->status] ?? 'secondary' }}-lt">
                                    {{ $statusLabels[$group->status] ?? str($group->status)->replace('_', ' ') }}
                                </span>
                            </td>
                            <td>
                                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.reservation-groups.show', $group) }}">
                                    Revisar
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-ui.table-card>
    @endif

    <x-ui.table-card title="Reservas">
        <table class="table table-vcenter">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Huesped</th>
                    <th>Alojamiento</th>
                    <th>Fechas</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th class="w-1"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reservations as $reservation)
                    <tr>
                        <td>
                            <a class="fw-bold" href="{{ route('admin.reservations.show', $reservation->id) }}">{{ $reservation->code }}</a>
                            @if ($reservation->hold_expires_at && $reservation->status === 'pending_payment')
                                <div class="text-muted small">Expira {{ $reservation->hold_expires_at->diffForHumans() }}</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $reservation->guest_name }}</div>
                            <div class="text-muted small">{{ $reservation->guest_email }}</div>
                            <div class="text-muted small">Canal: {{ $reservation->reservationChannel?->name ?: 'Sin canal' }}</div>
                        </td>
                        <td>
                            <div>{{ $reservation->space->title ?: $reservation->space->name }}</div>
                            @if ($reservation->rooms->count() > 1)
                                <div class="text-muted small">{{ $reservation->rooms->count() }} habitaciones seleccionadas</div>
                            @elseif ($reservation->rooms->count() === 1)
                                <div class="text-muted small">{{ $reservation->rooms->first()->title ?: $reservation->rooms->first()->name }}</div>
                            @elseif ($reservation->room)
                                <div class="text-muted small">{{ $reservation->room->title ?: $reservation->room->name }}</div>
                            @else
                                <div class="text-muted small">Espacio completo</div>
                            @endif
                        </td>
                        <td>
                            <div>{{ $reservation->check_in->toDateString() }} al {{ $reservation->check_out->toDateString() }}</div>
                            <div class="text-muted small">{{ $reservation->nights }} noche{{ $reservation->nights === 1 ? '' : 's' }} · {{ $reservation->guests }} persona{{ $reservation->guests === 1 ? '' : 's' }}</div>
                        </td>
                        <td>
                            <div>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</div>
                            <div class="text-muted small">Adelanto {{ money_format_decimal($reservation->advance_amount) }}</div>
                        </td>
                        <td>
                            <span class="badge bg-{{ $statusTones[$reservation->status] ?? 'secondary' }}-lt">
                                {{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ') }}
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.reservations.show', $reservation->id) }}">
                                Revisar
                            </a>
                        </td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="7" title="Sin reservas" message="No hay reservas para el filtro seleccionado." />
                @endforelse
            </tbody>
        </table>

        <x-slot:footer>
            {{ $reservations->links() }}
        </x-slot:footer>
    </x-ui.table-card>
@endsection
