<div class="occupancy-action-menu" data-occupancy-action-menu>
    <div class="occupancy-action-menu-header">
        <strong>{{ $resource_name }}</strong>
        {{-- <span>
            {{ $date->format('d/m/Y') }} · {{ $occupancy_state['label'] }}
            @if (! empty($occupancy_state['holder_guest_name']))
                · {{ $occupancy_state['holder_guest_name'] }}
            @endif
            @if (! empty($occupancy_state['check_in_code']))
                · {{ $occupancy_state['check_in_code'] }}
            @endif
            @if ($bed_type_label)
                · {{ $bed_unit ? 'Tipo de cama' : 'Camas' }}: {{ $bed_type_label }}
            @endif
        </span> --}}
    </div>

    @if (count($actions) === 0)
        <div class="occupancy-action-empty">
            {{ ($occupancy_state['status'] ?? null) === 'pending_check_out' ? 'Habitacion pendiente de check out.' : 'No se pueden gestionar fechas pasadas.' }}
        </div>
    @else
        <div class="occupancy-action-list">
            @foreach ($actions as $action)
                @if (! empty($action['url']))
                    <a
                        class="btn btn-outline-{{ $action['tone'] }} btn-sm occupancy-action-button"
                        href="{{ $action['url'] }}"
                    >
                        <i class="ti {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                    </a>
                @else
                    <button
                        class="btn btn-outline-{{ $action['tone'] }} btn-sm occupancy-action-button"
                        type="button"
                        data-occupancy-action="{{ $action['key'] }}"
                        data-space-id="{{ $space->id }}"
                        data-room-id="{{ $room?->id }}"
                        data-room-bed-unit-id="{{ $bed_unit?->id }}"
                        data-stay-id="{{ $occupancy_state['stay_id'] ?? '' }}"
                        data-reservation-group-id="{{ $occupancy_state['reservation_group_id'] ?? '' }}"
                        data-date="{{ $date->toDateString() }}"
                        @disabled(! empty($action['disabled']))
                    >
                        <i class="ti {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                    </button>
                @endif
            @endforeach
        </div>
    @endif
</div>
