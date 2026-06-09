<div class="occupancy-flow-panel" data-occupancy-flow-panel>
    <div class="occupancy-flow-summary">
        <div>
            <span class="text-body-secondary">Espacio / habitacion</span>
            <strong>{{ $resource_name }}</strong>
        </div>
        <div>
            <span class="text-body-secondary">Fecha seleccionada</span>
            <strong>{{ $date->format('d/m/Y') }}</strong>
        </div>
        <div>
            <span class="text-body-secondary">{{ $bed_unit ? 'Cama' : 'Estado actual' }}</span>
            <strong>{{ $bed_unit ? $bed_unit->label : $occupancy_state['label'] }}</strong>
        </div>
        @if ($bed_type_label)
            <div>
                <span class="text-body-secondary">{{ $bed_unit ? 'Tipo de cama' : 'Camas configuradas' }}</span>
                <strong>{{ $bed_type_label }}</strong>
            </div>
        @endif
    </div>

    <input type="hidden" name="space_id" value="{{ $space->id }}">
    <input type="hidden" name="space_room_id" value="{{ $room?->id }}">
    <input type="hidden" name="room_bed_unit_id" value="{{ $bed_unit?->id }}">
    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
    <input type="hidden" name="occupancy_status" value="{{ $occupancy_state['status'] }}">
    <input type="hidden" name="block_id" value="{{ $occupancy_state['block_id'] }}">

    <div class="alert alert-info mb-0">
        <div class="fw-semibold">{{ $title }}</div>
        <div>
            Estructura inicial del flujo. Aqui se desarrollara la gestion completa de {{ strtolower($title) }} para esta interseccion de la grilla.
        </div>
    </div>
</div>
