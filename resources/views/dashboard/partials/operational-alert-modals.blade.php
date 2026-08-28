@php
    $statusLabels = [
        'pending_payment' => 'Pago pendiente',
        'payment_under_review' => 'Pago en revision',
        'confirmed' => 'Confirmada',
        'checked_in' => 'Check-in iniciado',
    ];
@endphp

<div class="modal modal-blur fade" id="dashboardCheckOutModal" tabindex="-1" aria-labelledby="dashboardCheckOutModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title" id="dashboardCheckOutModalTitle">Habitaciones para check-out</h2><div class="text-body-secondary small">Salidas programadas para hoy</div></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr>@if (! $dashboardCompany)<th>Empresa</th>@endif<th>Habitacion</th><th>Huesped</th><th class="text-end">Personas</th></tr></thead>
                        <tbody>
                            @forelse ($alerts['check_out_rooms'] as $stay)
                                <tr>@if (! $dashboardCompany)<td>{{ $stay->company?->name ?: '-' }}</td>@endif<td class="fw-semibold">{{ $stayUnit($stay) }}</td><td>{{ $stay->holderGuest?->full_name ?: $stay->holderGuest?->name ?: 'Sin huesped' }}</td><td class="text-end">{{ $stay->people_count }}</td></tr>
                            @empty
                                <tr><td colspan="{{ $dashboardCompany ? 3 : 4 }}" class="text-center text-body-secondary py-4">No hay check-outs programados para hoy.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="dashboardBalanceModal" tabindex="-1" aria-labelledby="dashboardBalanceModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title" id="dashboardBalanceModalTitle">Habitaciones con saldo pendiente</h2><div class="text-body-secondary small">Estancias activas que requieren cobro</div></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr>@if (! $dashboardCompany)<th>Empresa</th>@endif<th>Habitacion</th><th>Huesped</th><th class="text-end">Saldo</th></tr></thead>
                        <tbody>
                            @forelse ($alerts['pending_balance_rooms'] as $stay)
                                <tr>@if (! $dashboardCompany)<td>{{ $stay->company?->name ?: '-' }}</td>@endif<td class="fw-semibold">{{ $stayUnit($stay) }}</td><td>{{ $stay->holderGuest?->full_name ?: $stay->holderGuest?->name ?: 'Sin huesped' }}</td><td class="text-end fw-semibold"><x-ui.money class="align-items-end" :amount="$stay->accountStatement?->balance ?? 0" :currency="$stay->accountStatement?->currency ?? $stay->currency" :exchange-rate="$stay->exchange_rate" /></td></tr>
                            @empty
                                <tr><td colspan="{{ $dashboardCompany ? 3 : 4 }}" class="text-center text-body-secondary py-4">No hay habitaciones con saldo pendiente.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal modal-blur fade" id="dashboardReservationsModal" tabindex="-1" aria-labelledby="dashboardReservationsModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title" id="dashboardReservationsModalTitle">Reservas para hoy</h2><div class="text-body-secondary small">{{ $alerts['pending_reservations'] }} pendientes de pago o revision</div></div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-vcenter mb-0">
                        <thead><tr>@if (! $dashboardCompany)<th>Empresa</th>@endif<th>Codigo</th><th>Habitacion</th><th>Huesped</th><th>Estado</th><th class="text-end">Personas</th></tr></thead>
                        <tbody>
                            @forelse ($alerts['reservations_today'] as $reservation)
                                <tr>
                                    @if (! $dashboardCompany)<td>{{ $reservation->company?->name ?: '-' }}</td>@endif
                                    <td class="fw-semibold">{{ $reservation->code }}</td>
                                    <td>{{ $reservationUnits($reservation) }}</td>
                                    <td>{{ $reservation->guest_name ?: 'Sin huesped' }}</td>
                                    <td><span class="badge {{ in_array($reservation->status, ['pending_payment', 'payment_under_review'], true) ? 'text-bg-warning' : 'text-bg-success' }}">{{ $statusLabels[$reservation->status] ?? str($reservation->status)->replace('_', ' ')->title() }}</span></td>
                                    <td class="text-end">{{ $reservation->guests }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ $dashboardCompany ? 5 : 6 }}" class="text-center text-body-secondary py-4">No hay reservas con ingreso para hoy.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
