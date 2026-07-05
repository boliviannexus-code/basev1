@extends('layouts.admin')

@section('title', 'Reserva '.$group->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reserva '.$group->code)
@section('page-subtitle', 'Reserva interna agrupada')

@section('content')
    @php
        $statusLabels = [
            'pending_payment' => 'Pendiente de pago',
            'payment_under_review' => 'Pago en revision',
            'confirmed' => 'Confirmada',
            'checked_in' => 'En check-in',
            'cancelled' => 'Cancelada',
        ];
        $statusTones = [
            'pending_payment' => 'warning',
            'payment_under_review' => 'info',
            'confirmed' => 'success',
            'checked_in' => 'primary',
            'cancelled' => 'secondary',
        ];
        $resourceLabel = function ($reservation): string {
            if ($reservation->bedUnitItems->isNotEmpty()) {
                return $reservation->bedUnitItems
                    ->map(fn ($item) => collect([
                        $reservation->space?->title ?: $reservation->space?->name,
                        $item->bedUnit?->room?->name ?: $item->bedUnit?->room?->title,
                        $item->bedUnit?->label,
                    ])->filter()->implode(' / '))
                    ->implode(', ');
            }

            if ($reservation->roomItems->isNotEmpty()) {
                return $reservation->roomItems
                    ->map(fn ($item) => collect([
                        $reservation->space?->title ?: $reservation->space?->name,
                        $item->room?->name ?: $item->room?->title,
                    ])->filter()->implode(' / '))
                    ->implode(', ');
            }

            return $reservation->space?->title ?: $reservation->space?->name ?: 'Recurso';
        };
    @endphp

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="fw-semibold mb-1">Revisa los datos de la reserva.</div>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ $occupancyUrl }}">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="reservation-admin-grid">
        <x-ui.card title="Detalle de reserva">
            <div class="card-body">
                <dl class="reservation-admin-dl">
                    <div><dt>Estado</dt><dd><span class="badge bg-{{ $statusTones[$group->status] ?? 'secondary' }}-lt">{{ $statusLabels[$group->status] ?? $group->status }}</span></dd></div>
                    <div><dt>Pago</dt><dd>{{ str($group->payment_status)->replace('_', ' ') }}</dd></div>
                    <div><dt>Canal</dt><dd>{{ $group->reservationChannel?->name ?: 'Sin canal' }}</dd></div>
                    <div><dt>Huesped</dt><dd>{{ $group->guest_name }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ $group->guest_email ?: 'Sin correo' }}</dd></div>
                    <div><dt>Telefono</dt><dd>{{ $group->guest_phone ?: 'Sin telefono' }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ $group->guest_document ?: 'Sin documento' }}</dd></div>
                    <div><dt>Fechas</dt><dd>{{ $group->check_in->toDateString() }} al {{ $group->check_out->toDateString() }}</dd></div>
                    <div><dt>Noches</dt><dd>{{ $group->nights }}</dd></div>
                    <div><dt>Personas</dt><dd>{{ $group->guests }}</dd></div>
                    <div><dt>Recursos</dt><dd>{{ $group->reservations->count() }}</dd></div>
                </dl>
            </div>
        </x-ui.card>

        <x-ui.card title="Resumen de pago">
            <div class="card-body">
                <dl class="reservation-admin-dl">
                    <div><dt>Total</dt><dd>{{ money_format_decimal($group->total_amount) }} {{ $group->currency }}</dd></div>
                    <div><dt>Adelanto</dt><dd>{{ money_format_decimal($group->advance_amount) }} {{ $group->currency }}</dd></div>
                    <div><dt>Saldo</dt><dd>{{ money_format_decimal($group->balance_amount) }} {{ $group->currency }}</dd></div>
                    <div><dt>Metodo</dt><dd>{{ $group->payment_method ?: 'Sin metodo' }}</dd></div>
                    <div><dt>Referencia</dt><dd>{{ $group->payment_reference ?: 'Sin referencia' }}</dd></div>
                </dl>
            </div>
        </x-ui.card>
    </div>

    <x-ui.table-card title="Recursos reservados" class="mt-3">
        @php
            $hasActiveReservationBlocks = $group->reservations
                ->flatMap(fn ($reservation) => collect([$reservation->occupancyBlock])
                    ->merge($reservation->roomItems->pluck('occupancyBlock'))
                    ->merge($reservation->bedUnitItems->pluck('occupancyBlock')))
                ->filter(fn ($block) => $block && $block->status === 'active' && ! $block->trashed())
                ->isNotEmpty();
            $checkInAlreadyRegistered = $group->status === 'checked_in' && ! $hasActiveReservationBlocks;
            $canStartCheckIn = $group->check_in->isSameDay(today()) && ! $checkInAlreadyRegistered;
            $canCancelReservation = ! in_array($group->status, ['cancelled'], true)
                && ($group->status !== 'checked_in' || $hasActiveReservationBlocks);
        @endphp

        @if ((auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage')) && $group->status !== 'cancelled')
            <x-slot:actions>
                <form action="{{ route('admin.reservation-groups.check-in', $group) }}" method="post" class="d-inline">
                    @csrf
                    <button class="btn btn-success btn-sm" type="submit" @disabled(! $canStartCheckIn) title="{{ $checkInAlreadyRegistered ? 'El check-in ya fue registrado' : ($canStartCheckIn ? 'Enviar reserva a check-in' : 'Disponible solo en la fecha de ingreso') }}">
                        <i class="ti ti-login me-1"></i>{{ $checkInAlreadyRegistered ? 'Check-in registrado' : ($group->status === 'checked_in' ? 'Continuar check-in' : 'Check-in') }}
                    </button>
                </form>
                @if ($group->status !== 'checked_in')
                    <button class="btn btn-primary btn-sm" form="reservation-resources-form" type="submit">
                        <i class="ti ti-device-floppy me-1"></i>Actualizar reserva
                    </button>
                @endif
            </x-slot:actions>
        @endif

        <form id="reservation-resources-form" action="{{ route('admin.reservation-groups.update', $group) }}" method="post">
            @csrf
            @method('patch')
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Recurso</th>
                        <th>Ingreso</th>
                        <th>Salida</th>
                        <th class="text-end">Precio noche</th>
                        <th class="text-end">Noches</th>
                        <th class="text-end">Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group->reservations as $reservation)
                        @php
                            $canEditReservation = (auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'))
                                && $group->status !== 'cancelled'
                                && $group->status !== 'checked_in'
                                && $reservation->status !== 'cancelled';
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-semibold">{{ $resourceLabel($reservation) }}</div>
                                <div class="text-muted small">{{ $reservation->code }}</div>
                            </td>
                            <td>{{ $reservation->check_in->toDateString() }}</td>
                            <td style="min-width: 11rem;">
                                <input
                                    class="form-control form-control-sm"
                                    type="date"
                                    name="reservations[{{ $reservation->id }}][check_out]"
                                    value="{{ old("reservations.{$reservation->id}.check_out", $reservation->check_out->toDateString()) }}"
                                    min="{{ $reservation->check_in->copy()->addDay()->toDateString() }}"
                                    @disabled(! $canEditReservation)
                                >
                            </td>
                            <td class="text-end" style="min-width: 10rem;">
                                <div class="input-group input-group-sm">
                                    <input
                                        class="form-control text-end"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="reservations[{{ $reservation->id }}][price_per_night]"
                                        value="{{ old("reservations.{$reservation->id}.price_per_night", number_format((float) $reservation->price_per_person, 2, '.', '')) }}"
                                        @disabled(! $canEditReservation)
                                    >
                                    <span class="input-group-text">{{ $reservation->currency }}</span>
                                </div>
                            </td>
                            <td class="text-end">{{ $reservation->nights }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</td>
                            <td><span class="badge bg-{{ in_array($reservation->status, ['confirmed', 'checked_in'], true) ? 'success' : ($reservation->status === 'cancelled' ? 'secondary' : 'warning') }}-lt">{{ str($reservation->status)->replace('_', ' ') }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </form>
    </x-ui.table-card>

    @if ($group->accountStatement)
        <x-ui.table-card title="Estado de cuenta" class="mt-3">
            <x-slot:actions>
                @if ((float) $group->accountStatement->balance > 0 && ! in_array($group->status, ['cancelled', 'checked_in'], true))
                    <a
                        class="btn btn-success btn-sm"
                        href="{{ route('admin.reservation-groups.payments.create', $group) }}"
                        data-modal-url="{{ route('admin.reservation-groups.payments.create', $group) }}"
                        data-modal-title="Registrar adelanto"
                    >
                        <i class="ti ti-cash-register me-1"></i>Registrar adelanto
                    </a>
                @endif
            </x-slot:actions>
            <table class="table table-vcenter">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Concepto</th>
                        <th>Tipo</th>
                        <th class="text-end">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($group->accountStatement->items->sortBy('date') as $item)
                        <tr class="{{ $item->status === 'cancelled' ? 'text-muted text-decoration-line-through' : '' }}">
                            <td>{{ $item->date?->toDateString() ?: '-' }}</td>
                            <td>{{ $item->description }}</td>
                            <td>{{ str($item->type)->replace('_', ' ') }}</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($item->total) }} {{ $item->currency }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Subtotal</th>
                        <th class="text-end">{{ money_format_decimal($group->accountStatement->subtotal) }} {{ $group->accountStatement->currency }}</th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Pagos</th>
                        <th class="text-end">{{ money_format_decimal($group->accountStatement->payments_total) }} {{ $group->accountStatement->currency }}</th>
                    </tr>
                    <tr>
                        <th colspan="3" class="text-end">Saldo</th>
                        <th class="text-end">{{ money_format_decimal($group->accountStatement->balance) }} {{ $group->accountStatement->currency }}</th>
                    </tr>
                </tfoot>
            </table>
        </x-ui.table-card>
    @endif

    @if ($group->notes)
        <x-ui.card title="Notas" class="mt-3">
            <div class="card-body">
                <p class="mb-0">{{ $group->notes }}</p>
            </div>
        </x-ui.card>
    @endif

    @can('reservations.manage')
        <x-ui.card title="Acciones" class="mt-3">
            <div class="card-body reservation-admin-actions">
                @if (in_array($group->status, ['pending_payment', 'payment_under_review'], true))
                    <form action="{{ route('admin.reservation-groups.confirm', $group) }}" method="post">
                        @csrf
                        @method('patch')
                        <button class="btn btn-success" type="submit">
                            <i class="ti ti-check me-1"></i>Confirmar reserva
                        </button>
                    </form>
                @endif

                @if ($canCancelReservation)
                    <form action="{{ route('admin.reservation-groups.cancel', $group) }}" method="post">
                        @csrf
                        @method('patch')
                        <input class="form-control" name="reason" placeholder="Motivo opcional de cancelacion">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="ti ti-calendar-x me-1"></i>Cancelar reserva
                        </button>
                    </form>
                @endif
            </div>
        </x-ui.card>
    @endcan
@endsection
