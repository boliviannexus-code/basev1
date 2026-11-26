@php
    if ($reservation) {
        $record = $reservation->reservation_group_id ? $reservation->reservationGroup : $reservation;
        $record->loadMissing($record instanceof \App\Models\ReservationGroup
            ? ['accountStatement.items', 'reservations.space', 'reservations.room', 'reservations.roomItems.room', 'reservations.bedUnitItems.bedUnit.room']
            : ['accountStatement.items', 'space', 'room', 'roomItems.room', 'bedUnitItems.bedUnit.room']);
        $reserved = $record instanceof \App\Models\ReservationGroup ? $record->reservations->whereNotIn('status', ['cancelled', 'no_show']) : collect([$record]);
        $resourceNames = $reserved->flatMap(function ($item) {
            $spaceName = $item->space?->title ?: $item->space?->name;
            if ($item->bedUnitItems->isNotEmpty()) {
                return $item->bedUnitItems->map(fn ($bed) => collect([$spaceName, $bed->bedUnit?->room?->name ?: $bed->bedUnit?->room?->title, $bed->bedUnit?->label])->filter()->implode(' / '));
            }
            if ($item->roomItems->isNotEmpty()) {
                return $item->roomItems->map(fn ($roomItem) => collect([$spaceName, $roomItem->room?->name ?: $roomItem->room?->title])->filter()->implode(' / '));
            }
            return collect([collect([$spaceName, $item->room?->name ?: $item->room?->title])->filter()->implode(' / ')]);
        })->filter()->unique()->implode(', ');
        $copyTexts = [];
        foreach (['es', 'en', 'fr', 'pt'] as $language) {
            $copyTexts[$language] = trim(view('reservations.admin.partials.copy-text', compact('record', 'resourceNames', 'language'))->render());
        }
    }
@endphp
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
                @if ($action['key'] === 'copy_reservation')
                    <button class="btn btn-outline-{{ $action['tone'] }} btn-sm occupancy-action-button" type="button" data-copy-reservation="{{ json_encode($copyTexts, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) }}">
                        <i class="ti {{ $action['icon'] }} me-1"></i>{{ $action['label'] }}
                    </button>
                @elseif (! empty($action['url']))
                    <a
                        class="btn btn-outline-{{ $action['tone'] }} btn-sm occupancy-action-button"
                        href="{{ $action['url'] }}"
                        @if (! empty($action['modal_url']))
                            data-modal-url="{{ $action['modal_url'] }}"
                            data-modal-title="{{ $action['modal_title'] ?? $action['label'] }}"
                            data-modal-size="{{ $action['modal_size'] ?? 'lg' }}"
                        @endif
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
                        data-block-id="{{ $occupancy_state['block_id'] ?? '' }}"
                        data-cleaning-stay-id="{{ $occupancy_state['cleaning_stay_id'] ?? '' }}"
                        data-reservation-id="{{ $occupancy_state['reservation_id'] ?? '' }}"
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
