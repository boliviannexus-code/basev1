<x-ui.table-card title="Resumen por hotel" class="mt-3">
    <table class="table table-sm table-hover align-middle mb-0 reservation-mobile-table">
        <thead><tr><th>Hotel / espacio</th><th class="text-end">Ocupaciones por día</th><th class="text-end">Habitaciones libres por día</th><th class="text-end">Personas alojadas por día</th><th class="text-end">Ocupabilidad</th></tr></thead>
        <tbody>
            @forelse ($occupancy['hotel_summary'] as $hotel)
                <tr><td data-label="Hotel / espacio" class="fw-semibold">{{ $hotel['name'] }}</td><td data-label="Ocupaciones por día" class="text-end">{{ $hotel['occupied'] }}</td><td data-label="Habitaciones libres por día" class="text-end">{{ $hotel['free'] }}</td><td data-label="Personas alojadas por día" class="text-end">{{ $hotel['people'] }}</td><td data-label="Ocupabilidad" class="text-end">{{ number_format($hotel['rate'], 2) }} %</td></tr>
            @empty
                <tr><td colspan="5" class="text-center text-body-secondary py-3">No hay espacios activos para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-ui.table-card>

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
