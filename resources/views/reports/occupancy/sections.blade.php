<div class="row g-3 mt-1">
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Alojamientos" :value="$occupancy['summary']['spaces']" icon="ti ti-home" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Capacidad-noches" :value="$occupancy['summary']['capacity_nights']" icon="ti ti-bed" tone="info" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Ocupadas" :value="$occupancy['summary']['occupied_person_nights']" icon="ti ti-user-check" tone="success" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Reservadas" :value="$occupancy['summary']['reserved_person_nights']" icon="ti ti-calendar-check" tone="primary" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Bloqueadas" :value="$occupancy['summary']['blocked_nights']" icon="ti ti-calendar-x" tone="warning" /></div>
    <div class="col-sm-6 col-xl-2"><x-ui.stat-card label="Ocupabilidad" :value="number_format((float) $occupancy['summary']['occupancy_rate'], 2).' %'" icon="ti ti-chart-line" tone="success" /></div>
</div>

<x-ui.table-card title="Estadias" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Ingreso</th><th>Salida</th><th>Alojamiento</th><th>Habitacion</th><th>Huesped</th><th class="text-end">Personas</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($occupancy['stays'] as $stay)
                    <tr>
                        <td>{{ $stay->check_in_date?->format('Y-m-d') }}</td>
                        <td>{{ $stay->check_out_date?->format('Y-m-d') }}</td>
                        <td>{{ $stay->space?->title ?: $stay->space?->name }}</td>
                        <td>{{ $stay->room?->title ?: $stay->room?->name ?: '-' }}</td>
                        <td>{{ $stay->holderGuest?->full_name ?? '-' }}</td>
                        <td class="text-end">{{ $stay->people_count }}</td>
                        <td>{{ $stay->status === 'occupied' ? 'Ocupada' : 'Check-out' }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin estadias en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.table-card>

<x-ui.table-card title="Reservas vigentes" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Ingreso</th><th>Salida</th><th>Codigo</th><th>Alojamiento</th><th>Huesped</th><th class="text-end">Personas</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse ($occupancy['reservations'] as $reservation)
                    <tr>
                        <td>{{ $reservation->check_in?->format('Y-m-d') }}</td>
                        <td>{{ $reservation->check_out?->format('Y-m-d') }}</td>
                        <td class="fw-semibold">{{ $reservation->code }}</td>
                        <td>{{ $reservation->space?->title ?: $reservation->space?->name }}</td>
                        <td>{{ $reservation->guest_name }}</td>
                        <td class="text-end">{{ $reservation->guests }}</td>
                        <td>{{ $reservation->status }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin reservas vigentes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.table-card>

<x-ui.table-card title="Bloqueos" class="mt-3">
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead><tr><th>Inicio</th><th>Fin</th><th>Alojamiento</th><th>Habitacion</th><th>Cama</th><th>Motivo</th><th>Usuario</th></tr></thead>
            <tbody>
                @forelse ($occupancy['blocks'] as $block)
                    <tr>
                        <td>{{ $block->start_date?->format('Y-m-d') }}</td>
                        <td>{{ $block->end_date?->format('Y-m-d') }}</td>
                        <td>{{ $block->space?->title ?: $block->space?->name }}</td>
                        <td>{{ $block->room?->title ?: $block->room?->name ?: '-' }}</td>
                        <td>{{ $block->bedUnit?->label ?: '-' }}</td>
                        <td>{{ $block->title ?: $block->description ?: $block->type ?: '-' }}</td>
                        <td>{{ $block->creator?->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td class="text-center text-body-secondary py-3" colspan="7">Sin bloqueos activos en el periodo.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-ui.table-card>
