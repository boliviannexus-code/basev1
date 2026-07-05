@extends('layouts.admin')

@section('title', 'Reserva '.$reservation->code.' | '.config('app.name', 'Base Admin'))
@section('page-title', 'Reserva '.$reservation->code)
@section('page-subtitle', 'Revision de solicitud publica y pago QR')

@section('content')
    @php
        $statusLabels = [
            'pending_payment' => 'Pendiente de pago',
            'payment_under_review' => 'Pago en revision',
            'confirmed' => 'Confirmada',
            'checked_in' => 'En check-in',
            'rejected' => 'Rechazada',
            'cancelled' => 'Cancelada',
            'expired' => 'Vencida',
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
        $activeExtraCharges = $reservation->extraCharges->where('status', 'active');
        $cancelledExtraCharges = $reservation->extraCharges->where('status', 'cancelled');
        $hasActiveReservationBlocks = collect([$reservation->occupancyBlock])
            ->merge($reservation->roomItems->pluck('occupancyBlock'))
            ->merge($reservation->bedUnitItems->pluck('occupancyBlock'))
            ->filter()
            ->unique('id')
            ->contains(fn ($block) => $block->status === 'active' && ! $block->trashed());
        $canAddReservationCharge = ! in_array($reservation->status, ['cancelled', 'rejected', 'expired'], true)
            && ($reservation->status !== 'checked_in' || $hasActiveReservationBlocks);
        $canCancelReservation = ! in_array($reservation->status, ['cancelled', 'rejected', 'expired'], true)
            && ($reservation->status !== 'checked_in' || $hasActiveReservationBlocks);
    @endphp

    <div class="mb-3">
        <a class="btn btn-outline-secondary btn-sm" href="{{ $occupancyUrl }}">
            <i class="ti ti-arrow-left me-1"></i>Volver
        </a>
    </div>

    <div class="reservation-admin-grid">
        <x-ui.card title="Detalle de reserva">
            <div class="card-body">
                <dl class="reservation-admin-dl">
                    <div><dt>Estado</dt><dd><span class="badge bg-{{ $statusTones[$reservation->status] ?? 'secondary' }}-lt">{{ $statusLabels[$reservation->status] ?? $reservation->status }}</span></dd></div>
                    <div><dt>Pago</dt><dd>{{ str($reservation->payment_status)->replace('_', ' ') }}</dd></div>
                    <div><dt>Canal</dt><dd>{{ $reservation->reservationChannel?->name ?: 'Sin canal asignado' }}</dd></div>
                    <div><dt>Huesped</dt><dd>{{ $reservation->guest_name }}</dd></div>
                    <div><dt>Correo</dt><dd>{{ $reservation->guest_email }}</dd></div>
                    <div><dt>Telefono</dt><dd>{{ $reservation->guest_phone ?: 'Sin telefono' }}</dd></div>
                    <div><dt>Pais</dt><dd>{{ $reservation->guest_country ?: 'Sin pais' }}</dd></div>
                    <div><dt>Documento</dt><dd>{{ $reservation->guest_document ?: 'Sin documento' }}</dd></div>
                    <div><dt>Alojamiento</dt><dd>{{ $reservation->space->title ?: $reservation->space->name }}</dd></div>
                    <div>
                        <dt>Recurso</dt>
                        <dd>
                            @if ($reservation->rooms->count() > 1)
                                {{ $reservation->rooms->count() }} habitaciones seleccionadas
                            @elseif ($reservation->rooms->count() === 1)
                                {{ $reservation->rooms->first()->title ?: $reservation->rooms->first()->name }}
                            @else
                                {{ $reservation->room ? ($reservation->room->title ?: $reservation->room->name) : 'Espacio completo' }}
                            @endif
                        </dd>
                    </div>
                    <div><dt>Fechas</dt><dd>{{ $reservation->check_in->toDateString() }} al {{ $reservation->check_out->toDateString() }}</dd></div>
                    <div><dt>Noches</dt><dd>{{ $reservation->nights }}</dd></div>
                    <div><dt>Personas</dt><dd>{{ $reservation->guests }}</dd></div>
                    <div><dt>Hold temporal</dt><dd>{{ $reservation->hold_expires_at ? $reservation->hold_expires_at->toDateTimeString() : 'Sin expiracion' }}</dd></div>
                </dl>
            </div>
        </x-ui.card>

        <x-ui.card title="Resumen de pago">
            <div class="card-body">
                <dl class="reservation-admin-dl">
                    <div><dt>Precio por noche</dt><dd>{{ money_format_decimal($reservation->price_per_person) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Subtotal</dt><dd>{{ money_format_decimal($reservation->subtotal_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Total</dt><dd>{{ money_format_decimal($reservation->total_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Adelanto requerido</dt><dd>{{ money_format_decimal($reservation->advance_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Saldo pendiente</dt><dd>{{ money_format_decimal($reservation->balance_amount) }} {{ $reservation->currency }}</dd></div>
                    <div><dt>Referencia</dt><dd>{{ $reservation->payment_reference ?: 'Sin referencia' }}</dd></div>
                </dl>

                @if ($reservation->roomItems->isNotEmpty())
                    <div class="mt-3">
                        <h4 class="h6">Habitaciones reservadas</h4>
                        <div class="public-chip-list">
                            @foreach ($reservation->roomItems as $item)
                                <span>{{ $item->room?->title ?: $item->room?->name }} · Cap. {{ $item->capacity }} · {{ money_format_decimal($item->subtotal_amount) }} {{ $reservation->currency }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($reservation->payment_proof_path)
                    <a class="btn btn-outline-primary w-100 mt-3" href="{{ Storage::disk('public')->url($reservation->payment_proof_path) }}" target="_blank" rel="noopener">
                        <i class="ti ti-file-search me-1"></i>Ver comprobante
                    </a>
                @else
                    <div class="alert alert-warning mt-3 mb-0">El huesped aun no adjunto comprobante.</div>
                @endif
            </div>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Cargos extras</h3>
            @can('reservations.manage')
                @if ($canAddReservationCharge)
                    <button
                        class="btn btn-primary btn-sm"
                        type="button"
                        data-modal-url="{{ route('admin.reservations.extra-charges.create', $reservation) }}"
                        data-modal-title="Agregar cargo extra"
                    >
                        <i class="ti ti-plus me-1"></i>Agregar cargo
                    </button>
                @endif
            @endcan
        </div>
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Categoria</th>
                        <th>Detalle</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Unitario</th>
                        <th class="text-end">Total</th>
                        <th class="text-end">Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activeExtraCharges as $charge)
                        <tr>
                            <td>{{ $charge->date?->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ $charge->category?->name ?: '-' }}</td>
                            <td>{{ $charge->detail }}</td>
                            <td class="text-end">{{ number_format((float) $charge->quantity, 2) }}</td>
                            <td class="text-end">{{ money_format_decimal($charge->unit_price) }} Bs</td>
                            <td class="text-end fw-semibold">{{ money_format_decimal($charge->total) }} Bs</td>
                            <td class="text-end"><span class="badge text-bg-success">Activo</span></td>
                            <td class="text-end">
                                @can('reservations.manage')
                                    <form method="POST" action="{{ route('reservation-extra-charges.cancel', $charge) }}" data-confirm-delete="Cancelar cargo extra?">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-danger btn-sm" type="submit">
                                            <i class="ti ti-x me-1"></i>Cancelar
                                        </button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-secondary py-4" colspan="8">No hay cargos extras registrados.</td>
                        </tr>
                    @endforelse
                    @foreach ($cancelledExtraCharges as $charge)
                        <tr class="text-secondary">
                            <td>{{ $charge->date?->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ $charge->category?->name ?: '-' }}</td>
                            <td>{{ $charge->detail }}</td>
                            <td class="text-end">{{ number_format((float) $charge->quantity, 2) }}</td>
                            <td class="text-end">{{ money_format_decimal($charge->unit_price) }} Bs</td>
                            <td class="text-end">{{ money_format_decimal($charge->total) }} Bs</td>
                            <td class="text-end"><span class="badge text-bg-secondary">Cancelado</span></td>
                            <td></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

    @can('reservations.manage')
        <x-ui.card title="Acciones de administracion" class="mt-3">
            <div class="card-body reservation-admin-actions">
                @if (in_array($reservation->status, ['pending_payment', 'payment_under_review'], true))
                    <form action="{{ route('admin.reservations.approve', $reservation->id) }}" method="post">
                        @csrf
                        @method('patch')
                        <button class="btn btn-success" type="submit">
                            <i class="ti ti-check me-1"></i>Aprobar pago y confirmar
                        </button>
                    </form>

                    <form action="{{ route('admin.reservations.reject', $reservation->id) }}" method="post">
                        @csrf
                        @method('patch')
                        <input class="form-control" name="reason" placeholder="Motivo opcional de rechazo">
                        <button class="btn btn-outline-danger" type="submit">
                            <i class="ti ti-x me-1"></i>Rechazar y liberar
                        </button>
                    </form>
                @endif

                @if ($canCancelReservation)
                    <form action="{{ route('admin.reservations.cancel', $reservation->id) }}" method="post">
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
