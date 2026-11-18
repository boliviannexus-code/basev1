@php
    $statusLabels = [
        'pending_payment' => 'Pendiente de pago',
        'payment_under_review' => 'Pago en revision',
        'confirmed' => 'Confirmada',
        'checked_in' => 'En check-in',
        'cancelled' => 'Cancelada',
        'no_show' => 'No se presentó',
    ];
    $statusTones = [
        'pending_payment' => 'warning',
        'payment_under_review' => 'info',
        'confirmed' => 'success',
        'checked_in' => 'primary',
        'cancelled' => 'secondary',
        'no_show' => 'danger',
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
    $hasActiveReservationBlocks = $group->reservations
        ->flatMap(fn ($reservation) => collect([$reservation->occupancyBlock])
            ->merge($reservation->roomItems->pluck('occupancyBlock'))
            ->merge($reservation->bedUnitItems->pluck('occupancyBlock')))
        ->filter(fn ($block) => $block && $block->status === 'active' && ! $block->trashed())
        ->isNotEmpty();
    $checkInAlreadyRegistered = $group->status === 'checked_in' && ! $hasActiveReservationBlocks;
    $canStartCheckIn = $group->check_in->isSameDay(today()) && ! $checkInAlreadyRegistered;
    $canEditReservation = (auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'))
        && ! in_array($group->status, ['cancelled', 'no_show', 'checked_in'], true);
    $canCancelReservation = ! in_array($group->status, ['cancelled', 'no_show'], true)
        && ($group->status !== 'checked_in' || $hasActiveReservationBlocks);
    $canMarkNoShow = in_array($group->status, ['pending_payment', 'payment_under_review', 'confirmed', 'checked_in'], true)
        && ($group->status !== 'checked_in' || $hasActiveReservationBlocks);
    $birthCountry = $countries->firstWhere('id', $group->guest_birth_country_id);
@endphp

<div class="reservation-read-panel">

@if ($errors->any())
    <div class="alert alert-danger">
        <div class="fw-semibold mb-1">No se pudo completar la accion.</div>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
    <a class="btn btn-outline-secondary btn-sm align-self-start" href="{{ $occupancyUrl }}">
        <i class="ti ti-arrow-left me-1"></i>Volver
    </a>

</div>

@include('reservations.admin.partials.read-header', [
    'record' => $group,
    'resourceNames' => $group->reservations->map(fn ($item) => $resourceLabel($item))->unique()->implode(', '),
    'resourceCount' => $group->reservations->whereNotIn('status', ['cancelled', 'no_show'])->count(),
    'paymentUrl' => (auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage'))
        && (float) ($group->accountStatement?->balance ?? 0) > 0
        && ! in_array($group->status, ['cancelled', 'no_show', 'checked_in'], true)
            ? route('admin.reservation-groups.payments.create', $group) : null,
    'actionsView' => (auth()->user()?->can('reservations.manage') || auth()->user()?->can('occupancy.manage')) ? 'reservations.admin.partials.group-read-actions' : null,
])

<div class="reservation-admin-grid mt-3">
    <x-ui.card title="Huesped titular">
        <div class="card-body">
            <dl class="check-in-read-dl">
                <div><dt>Nombre</dt><dd>{{ $group->guest_name }}</dd></div>
                <div><dt>Tipo documento</dt><dd>{{ $group->guest_document_type ? strtoupper($group->guest_document_type) : 'Sin tipo' }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $group->guest_document ?: 'Sin documento' }}</dd></div>
                <div><dt>Pais nacimiento</dt><dd>{{ $birthCountry ? $birthCountry->name.' ('.$birthCountry->iso_code.')' : 'Sin pais' }}</dd></div>
                <div><dt>Fecha nacimiento</dt><dd>{{ $group->guest_birth_date?->format('d/m/Y') ?: 'Sin fecha' }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $group->guest_email ?: 'Sin correo' }}</dd></div>
                <div><dt>Telefono</dt><dd>{{ $group->guest_phone ?: 'Sin telefono' }}</dd></div>
            </dl>
        </div>
    </x-ui.card>

    <x-ui.card title="Datos de reserva">
        <div class="card-body">
            <dl class="check-in-read-dl">
                <div><dt>Codigo</dt><dd>{{ $group->code }}</dd></div>
                <div><dt>Pago</dt><dd>{{ str($group->payment_status)->replace('_', ' ')->headline() }}</dd></div>
                <div><dt>Canal</dt><dd>{{ $group->reservationChannel?->name ?: 'Sin canal' }}</dd></div>
            </dl>
        </div>
    </x-ui.card>

</div>

<div class="mt-3">
    <x-ui.card title="Resumen de cuenta">
        <div class="card-body">
            <dl class="check-in-read-dl">
                <div><dt>Total</dt><dd><x-ui.money :amount="$group->total_amount" :currency="$group->currency" :inline="true" :exchange-rate="$currentExchangeRate?->rate" /></dd></div>
                <div><dt>Adelanto</dt><dd><x-ui.money :amount="$group->advance_amount" :currency="$group->currency" :inline="true" :exchange-rate="$currentExchangeRate?->rate" /></dd></div>
                <div><dt>Saldo</dt><dd><x-ui.money :amount="$group->balance_amount" :currency="$group->currency" :inline="true" :exchange-rate="$currentExchangeRate?->rate" /></dd></div>
                <div><dt>Metodo</dt><dd>{{ $group->payment_method ?: 'Sin metodo' }}</dd></div>
                <div><dt>Referencia</dt><dd>{{ $group->payment_reference ?: 'Sin referencia' }}</dd></div>
            </dl>
        </div>
    </x-ui.card>


</div>

@if ($group->accountStatement)
    <x-ui.table-card title="Estado de cuenta" class="mt-3">
        <table class="table table-vcenter reservation-mobile-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Tipo</th>
                    <th class="text-end">Importe</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($group->accountStatement->items->sortBy('date') as $item)
                    <tr class="{{ $item->status === 'cancelled' ? 'text-muted text-decoration-line-through' : '' }}">
                        <td data-label="Fecha">{{ $item->date?->format('d/m/Y') ?: '-' }}</td>
                        <td data-label="Concepto">{{ $item->description }}</td>
                        <td data-label="Tipo">{{ str($item->type)->replace('_', ' ')->headline() }}</td>
                        <td data-label="Importe" class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$item->total" :currency="$item->currency" :exchange-rate="$currentExchangeRate?->rate" /></td>
                    </tr>
                @empty
                    <x-ui.empty-row colspan="4" message="No hay movimientos registrados." />
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3" class="text-end">Subtotal</th>
                    <th class="text-end"><x-ui.money class="align-items-end" :amount="$group->accountStatement->subtotal" :currency="$group->accountStatement->currency" :exchange-rate="$currentExchangeRate?->rate" /></th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">Pagos</th>
                    <th class="text-end"><x-ui.money class="align-items-end" :amount="$group->accountStatement->payments_total" :currency="$group->accountStatement->currency" :exchange-rate="$currentExchangeRate?->rate" /></th>
                </tr>
                <tr>
                    <th colspan="3" class="text-end">Saldo</th>
                    <th class="text-end"><x-ui.money class="align-items-end" :amount="$group->accountStatement->balance" :currency="$group->accountStatement->currency" :exchange-rate="$currentExchangeRate?->rate" /></th>
                </tr>
            </tfoot>
        </table>
    </x-ui.table-card>
@endif

<x-ui.table-card title="Recursos reservados" class="mt-3">
    <table class="table table-vcenter reservation-mobile-table">
        <thead>
            <tr>
                <th>Recurso</th>
                <th>Ingreso</th>
                <th>Salida</th>
                <th class="text-end">Noches</th>
                <th class="text-end">Precio noche</th>
                <th class="text-end">Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($group->reservations as $reservation)
                <tr>
                    <td data-label="Recurso">
                        <div>
                            <div class="fw-semibold">{{ $resourceLabel($reservation) }}</div>
                            <div class="text-muted small">{{ $reservation->code }}</div>
                        </div>
                    </td>
                    <td data-label="Ingreso">{{ $reservation->check_in->format('d/m/Y') }}</td>
                    <td data-label="Salida">{{ $reservation->check_out->format('d/m/Y') }}</td>
                    <td data-label="Noches" class="text-end">{{ $reservation->nights }}</td>
                    <td data-label="Precio por noche" class="text-end"><x-ui.money class="align-items-end" :amount="$reservation->price_per_person" :currency="$reservation->currency" :exchange-rate="$currentExchangeRate?->rate" /></td>
                    <td data-label="Total" class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$reservation->total_amount" :currency="$reservation->currency" :exchange-rate="$currentExchangeRate?->rate" /></td>
                    <td data-label="Estado"><span class="badge bg-{{ in_array($reservation->status, ['confirmed', 'checked_in'], true) ? 'success' : ($reservation->status === 'no_show' ? 'danger' : ($reservation->status === 'cancelled' ? 'secondary' : 'warning')) }}-lt">{{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ') }}</span></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-ui.table-card>

@if ($group->notes)
    <x-ui.card title="Notas" class="mt-3">
        <div class="card-body">
            <p class="mb-0">{{ $group->notes }}</p>
        </div>
    </x-ui.card>
@endif



</div>
